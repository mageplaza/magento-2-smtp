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
use Laminas\Mail\Protocol\Exception\RuntimeException as LaminasProtocolRuntimeException;
use Laminas\Mail\Transport\Exception\RuntimeException as LaminasTransportRuntimeException;
use Laminas\Mime\Message as LaminasMimeMessage;
use Laminas\Mime\Mime as LaminasMime;
use Laminas\Mime\Part as LaminasMimePart;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\MimePartInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\GraphMailer;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\LogFactory;
use Mageplaza\Smtp\Observer\Email\SetTemplateVarsEntity;
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

                            $this->sendWithTransportRetry($message);

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
                $this->logSendFailureReason($e, $message);
                $this->emailLog($message, false, $e);
                throw new MailException(new Phrase($e->getMessage()), $e instanceof Exception ? $e : null);
            }
        }
    }

    /**
     * Send through the legacy (< 2.4.8) Laminas transport, retrying once if
     * the first attempt fails. resourceMail caches the transport as a
     * long-lived singleton, so a socket the remote server closed (e.g. idle
     * timeout) would otherwise make every subsequent send fail forever.
     * A second consecutive failure is not swallowed: it is rethrown so the
     * caller's existing catch block still logs and wraps it as before.
     *
     * @param $message
     *
     * @throws \Laminas\Mail\Protocol\Exception\RuntimeException
     * @throws \Laminas\Mail\Transport\Exception\RuntimeException
     */
    protected function sendWithTransportRetry($message)
    {
        $transport = $this->resourceMail->getTransport($this->_storeId);
        try {
            $transport->send($message);
        } catch (LaminasProtocolRuntimeException | LaminasTransportRuntimeException $e) {
            $this->resourceMail->resetTransport();
            $this->resourceMail->getTransport($this->_storeId)->send($message);
        }
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
        } elseif ($body instanceof LaminasMimeMessage) {
            // Magento < 2.4.8: Magento\Framework\Mail\Message::getBody() delegates to
            // Laminas\Mail\Message::getBody(), which returns a Laminas\Mime\Message,
            // not a Symfony\Component\Mime\Part\AbstractPart. Without this branch the
            // body/attachments are silently dropped and replaced with the
            // "No readable content." placeholder below.
            $this->collectLaminasMimeParts($body, $textParts, $attachments);
        } elseif (is_string($body) && $body !== '') {
            $textParts['plain'] = new TextPart($body);
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
     * Convert a Laminas\Mime\Message body (Magento < 2.4.8) into the same
     * $textParts/$attachments shape collectBodyParts() builds, so the
     * existing Email-assembly loop in convertToSymfonyEmail() can stay
     * unchanged.
     *
     * @param LaminasMimeMessage $mimeMessage
     * @param $textParts
     * @param $attachments
     */
    protected function collectLaminasMimeParts(LaminasMimeMessage $mimeMessage, &$textParts, &$attachments)
    {
        foreach ($mimeMessage->getParts() as $part) {
            // Magento's own EmailMessage/MimeMessage (used by TransportBuilder) hand
            // Laminas\Mime\Message::setParts() an array of Magento\Framework\Mail\MimePart
            // objects, not Laminas\Mime\Part -- they only happen to expose the same
            // accessor methods (getType/getDisposition/getFileName/getCharset/
            // getRawContent). A raw Laminas\Mime\Part is also accepted for callers that
            // build the body directly with Laminas.
            if (!$part instanceof LaminasMimePart && !$part instanceof MimePartInterface) {
                continue;
            }

            $type = strtolower((string) $part->getType());
            $isInlineText = ($type === 'text/html' || $type === 'text/plain')
                && $part->getDisposition() !== LaminasMime::DISPOSITION_ATTACHMENT;

            if ($isInlineText) {
                $subtype = $type === 'text/html' ? 'html' : 'plain';
                if (!isset($textParts[$subtype])) {
                    $textParts[$subtype] = new TextPart(
                        $part->getRawContent(),
                        $part->getCharset() ?: 'utf-8',
                        $subtype
                    );
                }

                continue;
            }

            $attachments[] = new DataPart(
                $part->getRawContent(),
                $part->getFileName(),
                $type ?: 'application/octet-stream'
            );
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
     * Log why a send failed. Only the exception message (capped at 1000
     * chars), store id, recipient and subject are logged -- never auth
     * credentials/tokens. Recipient/subject extraction is best-effort so a
     * problem here can never mask the original exception.
     *
     * @param \Throwable $e
     * @param $message
     */
    protected function logSendFailureReason(\Throwable $e, $message)
    {
        $recipient = '';
        try {
            $recipient = $this->getRecipient($message);
        } catch (\Throwable $ignored) {
            // Ignore: recipient extraction failing must not hide the real error.
        }

        $subject = '';
        try {
            if (is_object($message) && method_exists($message, 'getSubject')) {
                $subject = (string) $message->getSubject();
            }
        } catch (\Throwable $ignored) {
            // Ignore: subject extraction failing must not hide the real error.
        }

        $this->logger->error(sprintf(
            'Mageplaza_Smtp: failed to send email (store id: %s, to: %s, subject: "%s"): %s',
            $this->_storeId,
            $recipient,
            $subject,
            mb_substr($e->getMessage(), 0, 1000)
        ));
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
     * @param \Throwable|null $exception The send failure, if any -- its message (capped at 1000
     *                                   chars, never auth credentials/tokens) is stored as
     *                                   error_message so admins can see why a send failed
     *                                   without digging through system.log.
     */
    protected function emailLog($message, $status = true, ?\Throwable $exception = null)
    {
        // Always consume the registry, even if logging ends up disabled below -- otherwise a
        // captured entity would leak onto the next, unrelated email sent in this request.
        $entityData = $this->consumeEmailEntity();

        if ($this->helper->isEnabled($this->_storeId) && $this->resourceMail->isEnableEmailLog($this->_storeId)) {
            /** @var Log $log */
            $log   = $this->logFactory->create();
            $extra = $exception ? ['error_message' => mb_substr($exception->getMessage(), 0, 1000)] : [];
            $extra = array_merge($extra, $entityData);
            try {
                if ($this->helper->versionCompare('2.4.8')) {
                    if (!$message instanceof Email) {
                        $message = $this->convertToSymfonyEmail($message);
                    }

                    $log->saveLogSymfony($message, $status, $this->_storeId, $extra);
                } else {
                    $log->saveLog($message, $status, $this->_storeId, $extra);
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
     * Read back the {entity_type, entity_id} Observer\Email\SetTemplateVarsEntity stashed in
     * the registry for the email currently being sent, and clear the key. Returns [] when
     * nothing was captured (e.g. non-sales email, or the observer swallowed a problem).
     *
     * @return array
     */
    protected function consumeEmailEntity(): array
    {
        try {
            $entityData = $this->registry->registry(SetTemplateVarsEntity::REGISTRY_KEY);
            if ($entityData) {
                $this->registry->unregister(SetTemplateVarsEntity::REGISTRY_KEY);

                return [
                    'entity_type' => $entityData['entity_type'] ?? null,
                    'entity_id'   => $entityData['entity_id'] ?? null,
                ];
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return [];
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
