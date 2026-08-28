<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Mail;

use Closure;
use Exception;
use Laminas\Mail\Message;
use Laminas\Mail\Protocol\Smtp as SmtpProtocol;
use Laminas\Mail\Transport\Smtp;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\GraphMailer;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\LogFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\RawMessage;
use Zend_Exception;

/**
 * Class Transport
 * @package Mageplaza\Smtp\Mail
 */
class Transport
{
    const ERROR_CAUSE_LIMIT = 3;

    const ERROR_MESSAGE_LIMIT = 2000;

    /**
     * @var int Store Id
     */
    protected $_storeId;

    /**
     * @var Mail
     */
    protected $resourceMail;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * @var Registry $registry
     */
    protected $registry;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var GraphMailer
     */
    protected $graphMailer;

    /**
     * Transport constructor.
     *
     * @param Mail $resourceMail
     * @param LogFactory $logFactory
     * @param Registry $registry
     * @param Data $helper
     * @param LoggerInterface $logger
     * @param GraphMailer $graphMailer
     */
    public function __construct(
        Mail $resourceMail,
        LogFactory $logFactory,
        Registry $registry,
        Data $helper,
        LoggerInterface $logger,
        GraphMailer $graphMailer
    ) {
        $this->resourceMail = $resourceMail;
        $this->logFactory   = $logFactory;
        $this->registry     = $registry;
        $this->helper       = $helper;
        $this->logger       = $logger;
        $this->graphMailer  = $graphMailer;
    }

    /**
     * Around send message
     *
     * @param TransportInterface $subject
     * @param Closure $proceed
     *
     * @throws MailException
     * @throws Zend_Exception
     */
    public function aroundSendMessage(TransportInterface $subject, Closure $proceed)
    {
        $this->_storeId = $this->registry->registry('mp_smtp_store_id');
        if (!$this->resourceMail->isModuleEnable($this->_storeId)) {
            $proceed();

            return;
        }
        $message = $this->getMessage($subject);
        if (!$this->validateBlacklist($message)) {
            try {
                if (!$this->resourceMail->isDeveloperMode($this->_storeId)) {
                    // Check if we should use Microsoft Graph API
                    $smtpOptions       = $this->resourceMail->getSmtpOptions($this->_storeId);
                    $shouldUseGraphApi = $this->helper->shouldUseGraphApi($this->_storeId, $smtpOptions);

                    if ($shouldUseGraphApi) {
                        // Convert to Symfony Email if needed
                        if (!$message instanceof Email) {
                            $message = $this->convertToSymfonyEmail($message);
                        }

                        $this->graphMailer->sendEmail($message, $this->_storeId, $smtpOptions);
                    } else {
                        // Use SMTP transport (existing logic)
                        if ($this->helper->versionCompare('2.4.8')) {
                            $transport = $this->resourceMail->getSymfonyTransport($this->_storeId);
                            $mailer    = new Mailer($transport);
                            $mailer->send($this->resolveSymfonyMessage($message));
                        } else {
                            if ($this->helper->versionCompare('2.2.8')) {
                                $message = Message::fromString($message->getRawMessage())->setEncoding('utf-8');
                            }
                            $message = $this->resourceMail->processMessage($message, $this->_storeId);
                            if ($this->helper->versionCompare('2.3.3')) {
                                $message->getHeaders()->removeHeader("Content-Disposition");
                            }

                            $this->sendWithRetry($message);

                            if ($this->helper->versionCompare('2.2.8')) {
                                $messageTmp = $this->getMessage($subject);
                                if ($messageTmp && is_object($messageTmp)) {
                                    $body = $messageTmp->getBody();
                                    if (is_object($body) && $body->isMultiPart()) {
                                        $message->setBody($body->getPartContent("0"));
                                    }
                                }
                            }
                        }
                    }
                }

                $this->emailLog($message);
            } catch (\Throwable $e) {
                $errorMessage = $this->describeSendFailure($e);

                $this->logger->error(
                    'Mageplaza_Smtp: failed to send email. ' . $errorMessage,
                    [
                        'store_id'  => $this->_storeId,
                        'recipient' => $this->getRecipient($message),
                        'exception' => $e,
                    ]
                );

                $this->emailLog($message, false, $errorMessage);
                throw new MailException(new Phrase($e->getMessage()), $e instanceof Exception ? $e : null);
            }
        }
    }

    /**
     * @param $message
     *
     * @throws Zend_Exception
     * @throws \Throwable
     */
    protected function sendWithRetry($message)
    {
        $transport = $this->resourceMail->getTransport($this->_storeId);

        if (!$this->isConnectionAlive($transport)) {
            $transport = $this->resourceMail
                ->resetTransport()
                ->getTransport($this->_storeId);
        }

        $transport->send($message);
    }

    /**
     * @param $transport
     *
     * @return bool
     */
    protected function isConnectionAlive($transport)
    {
        if (!$transport instanceof Smtp || !method_exists($transport, 'getConnection')) {
            return true;
        }

        $connection = $transport->getConnection();
        if (!$connection instanceof SmtpProtocol || !$connection->hasSession()) {
            return true;
        }

        try {
            $connection->noop();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Mageplaza_Smtp: SMTP connection is no longer usable, reconnecting. ' . $e->getMessage(),
                ['store_id' => $this->_storeId]
            );

            return false;
        }

        return true;
    }

    /**
     * @param \Throwable $e
     *
     * @return string
     */
    protected function describeSendFailure(\Throwable $e)
    {
        $parts = [];

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $part = get_class($current);

            $code = $current->getCode();
            if ($code) {
                $part .= ' [' . $code . ']';
            }

            $text = trim($current->getMessage());
            if ($text !== '') {
                $part .= ': ' . $text;
            }

            $parts[] = $part;

            if (count($parts) >= self::ERROR_CAUSE_LIMIT) {
                break;
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * @param $laminasMessage
     *
     * @return Email
     */
    protected function convertToSymfonyEmail($laminasMessage)
    {
        $headers = ($laminasMessage !== null && method_exists($laminasMessage, 'getSymfonyMessage'))
            ? clone $laminasMessage->getSymfonyMessage()->getHeaders()
            : null;
        $email = $headers ? new Email($headers) : new Email();

        if ($laminasMessage === null) {
            return $email->text('No readable content.');
        }

        $fromList = $laminasMessage->getFrom();
        if (is_array($fromList) && count($fromList)) {
            $from = $fromList[0];
            $email->from(new Address($from->getEmail(), $from->getName()));
        }

        $to = $laminasMessage->getTo();
        if (is_array($to)) {
            $addresses = [];
            foreach ($to as $toAddress) {
                $addresses[] = new Address($toAddress->getEmail(), $toAddress->getName());
            }
            $email->to(...$addresses);
        }

        $email->subject((string) $laminasMessage->getSubject());

        try {
            $body = $laminasMessage->getBody();
        } catch (\Throwable $e) {
            $body = null;
        }

        $textParts   = [];
        $attachments = [];
        if ($body instanceof AbstractPart) {
            $this->collectBodyParts($body, $textParts, $attachments);
        }

        if ($textParts || $attachments) {
            foreach ($textParts as $subtype => $part) {
                try {
                    $contentType = $part->getPreparedHeaders()->get('Content-Type');
                    $charset     = ($contentType instanceof ParameterizedHeader
                        ? $contentType->getParameter('charset')
                        : null) ?: 'utf-8';
                } catch (\Throwable $e) {
                    $charset = 'utf-8';
                }

                if ($subtype === 'html') {
                    $email->html($part->getBody(), $charset);
                } else {
                    $email->text($part->getBody(), $charset);
                }
            }

            foreach ($attachments as $attachment) {
                if ($attachment instanceof DataPart) {
                    $dataPart = $attachment;
                } else {
                    $dataPart = new DataPart(
                        $attachment->getBody(),
                        null,
                        $attachment->getMediaType() . '/' . $attachment->getMediaSubtype()
                    );
                }
                $email->addPart(clone $dataPart);
            }
        } elseif ($body instanceof AbstractPart) {
            $email->setBody($body);
        } else {
            $email->text('No readable content.');
        }

        if ($laminasMessage->getCc()) {
            $ccAddresses = [];
            foreach ($laminasMessage->getCc() as $ccAddress) {
                $ccAddresses[] = new Address($ccAddress->getEmail(), $ccAddress->getName());
            }
            $email->cc(...$ccAddresses);
        }

        if ($laminasMessage->getBcc()) {
            $bccAddresses = [];
            foreach ($laminasMessage->getBcc() as $bccAddress) {
                $bccAddresses[] = new Address($bccAddress->getEmail(), $bccAddress->getName());
            }
            $email->bcc(...$bccAddresses);
        }

        if ($laminasMessage->getReplyTo()) {
            $replyToAddresses = [];
            foreach ($laminasMessage->getReplyTo() as $replyTo) {
                $replyToAddresses[] = new Address($replyTo->getEmail(), $replyTo->getName());
            }
            $email->replyTo(...$replyToAddresses);
        }

        return $email;
    }

    /**
     * @param $part
     * @param $textParts
     * @param $attachments
     */
    protected function collectBodyParts($part, &$textParts, &$attachments)
    {
        if ($part instanceof DataPart) {
            if (!in_array($part, $attachments, true)) {
                $attachments[] = $part;
            }

            return;
        }

        if ($part instanceof TextPart) {
            $subtype = $part->getMediaSubtype();
            if ($subtype === 'html' || $subtype === 'plain') {
                if (!isset($textParts[$subtype])) {
                    $textParts[$subtype] = $part;
                }

                return;
            }

            if (!in_array($part, $attachments, true)) {
                $attachments[] = $part;
            }

            return;
        }

        if ($part instanceof AbstractMultipartPart) {
            foreach ($part->getParts() as $childPart) {
                $this->collectBodyParts($childPart, $textParts, $attachments);
            }
        }
    }

    /**
     * Get message
     *
     * @param TransportInterface $transport
     *
     * @return mixed|null
     */
    protected function getMessage($transport)
    {
        if ($this->helper->versionCompare('2.2.0')) {
            return $transport->getMessage();
        }

        try {
            $reflectionClass = new ReflectionClass($transport);
            $message         = $reflectionClass->getProperty('_message');
        } catch (Exception $e) {
            return null;
        }

        $message->setAccessible(true);

        return $message->getValue($transport);
    }

    /**
     * @param $message
     *
     * @return RawMessage
     */
    protected function resolveSymfonyMessage($message)
    {
        if ($message instanceof RawMessage) {
            return $message;
        }

        if ($message !== null && method_exists($message, 'getSymfonyMessage')) {
            return $message->getSymfonyMessage();
        }

        return $this->convertToSymfonyEmail($message);
    }

    /**
     * @param EmailMessage $message
     *
     * @return string
     */
    public function getRecipient($message)
    {
        $emails = [];
        if ($message->getTo()) {
            foreach ($message->getTo() as $address) {
                $emails[] = $address->getEmail();
            }
        }

        return implode(',', $emails);
    }

    /**
     * @param EmailMessage $message
     *
     * @return bool
     */
    public function validateBlacklist($message)
    {
        $result = false;
        if ($this->helper->isTestEmail()) {
            return $result;
        }

        $blacklist = $this->helper->getBlacklist();
        if ($blacklist) {
            $recipient = $this->getRecipient($message);
            $patterns  = array_unique(explode(PHP_EOL, $blacklist));
            foreach ($patterns as $pattern) {
                try {
                    if (preg_match($pattern, $recipient)) {
                        $result = true;
                        break;
                    }
                } catch (Exception $e) {
                    // Ignore validate if the pattern is error
                    continue;
                }
            }
        }

        return $result;
    }

    /**
     * Save Email Sent
     *
     * @param $message
     * @param bool $status
     */
    protected function emailLog($message, $status = true, $errorMessage = null)
    {
        if ($this->helper->isEnabled($this->_storeId) && $this->resourceMail->isEnableEmailLog($this->_storeId)) {
            /** @var Log $log */
            $log = $this->logFactory->create();

            if ($errorMessage !== null && $errorMessage !== '') {
                $log->setErrorMessage(mb_substr($errorMessage, 0, self::ERROR_MESSAGE_LIMIT));
            }

            try {
                if ($this->helper->versionCompare('2.4.8')) {
                    if (!$message instanceof Email) {
                        $message = $this->convertToSymfonyEmail($message);
                    }

                    $log->saveLogSymfony($message, $status, $this->_storeId);
                } else {
                    $log->saveLog($message, $status, $this->_storeId);
                }

                if ($status) {
                    $this->saveLogIdForAbandonedCart($log);
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param Log $log
     */
    protected function saveLogIdForAbandonedCart($log)
    {
        try {
            $quote = $this->registry->registry('smtp_abandoned_cart');

            if ($quote) {
                $ids = $quote->getMpSmtpAceLogIds() ?
                    $quote->getMpSmtpAceLogIds() . ',' . $log->getId() : $log->getId();
                $quote->setMpSmtpAceSent(1)->setMpSmtpAceLogIds($ids)->save();
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
