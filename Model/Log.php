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

namespace Mageplaza\Smtp\Model;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Decode;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\MimeMessageInterface as MagentoMimeMessage;
use Magento\Framework\Mail\MimePartInterface as MagentoMimePart;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\ResourceModel\LogAttachment\CollectionFactory as AttachmentCollectionFactory;
use Mageplaza\Smtp\Model\Source\Status;
use Mageplaza\Smtp\Model\EmailSentFlagUpdater;

/**
 * Persists a record of each outgoing email (recipients, subject, content, status) together
 * with its attachments, and supports resending a previously logged email.
 * @package Mageplaza\Smtp\Model
 */
class Log extends AbstractModel
{
    const MIME_MAX_DEPTH = 5;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var Mail
     */
    protected $mailResource;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LogAttachmentFactory
     */
    protected $attachmentFactory;

    /**
     * @var AttachmentCollectionFactory
     */
    protected $attachmentCollectionFactory;

    /**
     * @var array
     */
    protected $pendingAttachments = [];

    /**
     * @var EmailSentFlagUpdater
     */
    protected $emailSentFlagUpdater;

    /**
     * Log constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param TransportBuilder $transportBuilder
     * @param Mail $mailResource
     * @param Data $helper
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param LogAttachmentFactory|null $attachmentFactory
     * @param AttachmentCollectionFactory|null $attachmentCollectionFactory
     * @param EmailSentFlagUpdater|null $emailSentFlagUpdater
     */
    public function __construct(
        Context $context,
        Registry $registry,
        TransportBuilder $transportBuilder,
        Mail $mailResource,
        Data $helper,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?LogAttachmentFactory $attachmentFactory = null,
        ?AttachmentCollectionFactory $attachmentCollectionFactory = null,
        ?EmailSentFlagUpdater $emailSentFlagUpdater = null
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->_transportBuilder    = $transportBuilder;
        $this->mailResource         = $mailResource;
        $this->helper               = $helper;
        $this->emailSentFlagUpdater = $emailSentFlagUpdater;

        $this->attachmentFactory           = $attachmentFactory;
        $this->attachmentCollectionFactory = $attachmentCollectionFactory;
    }

    /**
     * Initialize the resource model for this log entity.
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourceModel\Log::class);
    }

    /**
     * Save email logs
     *
     * @param mixed $message
     * @param int $status
     * @param int|null $storeId
     * @param array $extra Optional extra columns to persist alongside the log row
     *                     (currently: error_message).
     * @param string|null $fullBody
     */
    public function saveLog($message, $status, $storeId = null, array $extra = [], $fullBody = null)
    {
        if ($this->helper->versionCompare('2.2.8')) {
            if ($message->getSubject()) {
                $this->setSubject($message->getSubject());
            }

            $from = $message->getFrom();
            if (count($from)) {
                if (is_array($from)) {
                    $sender = reset($from);
                    $name   = (string) $sender->getName();
                    $email  = $sender->getEmail();
                    $this->setSender($name . ' <' . $email . '>');
                } else {
                    $from->rewind();
                    $this->setSender($from->current()->getName() . ' <' . $from->current()->getEmail() . '>');
                }
            }

            $toArr = [];
            foreach ($message->getTo() as $toAddr) {
                $toArr[] = $toAddr->getEmail();
            }
            $this->setRecipient(implode(',', $toArr));

            $ccArr = [];
            foreach ($message->getCc() as $ccAddr) {
                $ccArr[] = $ccAddr->getEmail();
            }
            $this->setCc(implode(',', $ccArr));

            $bccArr = [];
            foreach ($message->getBcc() as $bccAddr) {
                $bccArr[] = $bccAddr->getEmail();
            }
            $this->setBcc(implode(',', $bccArr));

            $parts   = $this->extractParts($message, $fullBody);
            $content = $parts['html'];

            if ($this->helper->versionCompare('2.3.3')) {
                $content = quoted_printable_decode($content);
            }

            $content                  = htmlspecialchars($content);
            $this->pendingAttachments = $parts['attachments'];
        } else {
            $headers = $message->getHeaders();

            if (isset($headers['Subject'][0])) {
                $this->setSubject($headers['Subject'][0]);
            }

            if (isset($headers['From'][0])) {
                $this->setSender($headers['From'][0]);
            }

            if (isset($headers['To'])) {
                $recipient = $headers['To'];
                if (isset($recipient['append'])) {
                    unset($recipient['append']);
                }
                $this->setRecipient(implode(', ', $recipient));
            }

            if (isset($headers['Cc'])) {
                $cc = $headers['Cc'];
                if (isset($cc['append'])) {
                    unset($cc['append']);
                }
                $this->setCc(implode(', ', $cc));
            }

            if (isset($headers['Bcc'])) {
                $bcc = $headers['Bcc'];
                if (isset($bcc['append'])) {
                    unset($bcc['append']);
                }
                $this->setBcc(implode(', ', $bcc));
            }

            $body = $message->getBodyHtml();
            if (is_object($body)) {
                $content = htmlspecialchars($body->getRawContent());
            } else {
                $content = htmlspecialchars($message->getBody()->getRawContent());
            }
        }

        $this->setEmailContent($content)
            ->setStatus($status)
            ->setStoreId($storeId ?? Store::DEFAULT_STORE_ID);
        $this->applyExtraData($extra);
        $this->save();

        $this->saveAttachments();
    }

    /**
     * Save email logs magento 2.4.8 and above
     *
     * @param mixed $message
     * @param int $status
     * @param int $storeId
     * @param array $extra Optional extra columns to persist alongside the log row
     *                     (currently: error_message).
     */
    public function saveLogSymfony($message, $status, $storeId = Store::DEFAULT_STORE_ID, array $extra = [])
    {
        if ($message->getSubject()) {
            $this->setSubject($message->getSubject());
        }

        $from = $message->getFrom();
        if (is_array($from) && count($from)) {
            $firstFrom = reset($from);
            $name      = method_exists($firstFrom, 'getName') ? $firstFrom->getName() : '';
            $email     = method_exists($firstFrom, 'getAddress')
                ? $firstFrom->getAddress()
                : (method_exists($firstFrom, 'getEmail') ? $firstFrom->getEmail() : '');
            $this->setSender(trim($name . ' <' . $email . '>'));
        }

        $toArr = [];
        foreach ($message->getTo() as $toAddr) {
            $toArr[] = method_exists($toAddr, 'getAddress') ? $toAddr->getAddress() : $toAddr->getEmail();
        }
        $this->setRecipient(implode(',', $toArr));

        $ccArr = [];
        foreach ($message->getCc() ?: [] as $ccAddr) {
            $ccArr[] = method_exists($ccAddr, 'getAddress') ? $ccAddr->getAddress() : $ccAddr->getEmail();
        }
        $this->setCc(implode(',', $ccArr));

        $bccArr = [];
        foreach ($message->getBcc() ?: [] as $bccAddr) {
            $bccArr[] = method_exists($bccAddr, 'getAddress') ? $bccAddr->getAddress() : $bccAddr->getEmail();
        }
        $this->setBcc(implode(',', $bccArr));

        $content  = '';
        $htmlBody = method_exists($message, 'getHtmlBody') ? $message->getHtmlBody() : null;
        $textBody = method_exists($message, 'getTextBody') ? $message->getTextBody() : null;

        if ($htmlBody) {
            $content = htmlspecialchars($htmlBody);
        } elseif ($textBody) {
            $content = htmlspecialchars($textBody);
        }

        $this->pendingAttachments = $this->extractSymfonyAttachments($message);

        $this->setEmailContent($content)
            ->setStatus($status)
            ->setStoreId($storeId);
        $this->applyExtraData($extra);
        $this->save();

        $this->saveAttachments();
    }

    /**
     * Resolve the attachment factory, falling back to the object manager.
     *
     * @return LogAttachmentFactory
     */
    protected function getAttachmentFactory()
    {
        if ($this->attachmentFactory === null) {
            $this->attachmentFactory = ObjectManager::getInstance()->get(LogAttachmentFactory::class);
        }

        return $this->attachmentFactory;
    }

    /**
     * Resolve the email-sent flag updater, falling back to the object manager.
     *
     * @return EmailSentFlagUpdater
     */
    protected function getEmailSentFlagUpdater()
    {
        if ($this->emailSentFlagUpdater === null) {
            $this->emailSentFlagUpdater = ObjectManager::getInstance()->get(EmailSentFlagUpdater::class);
        }

        return $this->emailSentFlagUpdater;
    }

    /**
     * Resolve the attachment collection factory, falling back to the object manager.
     *
     * @return AttachmentCollectionFactory
     */
    protected function getAttachmentCollectionFactory()
    {
        if ($this->attachmentCollectionFactory === null) {
            $this->attachmentCollectionFactory = ObjectManager::getInstance()
                ->get(AttachmentCollectionFactory::class);
        }

        return $this->attachmentCollectionFactory;
    }

    /**
     * Extract the HTML body and attachments from a legacy Laminas mail message.
     *
     * @param mixed $message
     * @param string|null $fullBody
     *
     * @return array ['html' => string, 'attachments' => array]
     */
    protected function extractParts($message, $fullBody = null)
    {
        $html        = '';
        $attachments = [];

        $body = $fullBody;

        if ($body === null) {
            try {
                $body = $message->getBody();
            } catch (\Throwable $e) {
                $body = null;
            }
        }

        if (!$body instanceof MimeMessage) {
            if (is_string($body) && $body !== '') {
                return ['html' => $body, 'attachments' => []];
            }

            try {
                return ['html' => (string) $message->getBodyText(), 'attachments' => []];
            } catch (\Throwable $e) {
                return ['html' => '', 'attachments' => []];
            }
        }

        $collected = ['html' => '', 'plain' => '', 'attachments' => []];
        $this->collectMimeParts($body->getParts(), $collected);

        return [
            'html'        => $collected['html'] !== '' ? $collected['html'] : $collected['plain'],
            'attachments' => $collected['attachments'],
        ];
    }

    /**
     * Recursively walk MIME parts, collecting the HTML/plain body and any attachments.
     *
     * @param array $parts
     * @param array $collected
     * @param int $depth
     *
     * @return void
     */
    protected function collectMimeParts($parts, array &$collected, $depth = 0)
    {
        if ($depth > self::MIME_MAX_DEPTH) {
            return;
        }

        foreach ($parts as $part) {
            $normalized = is_array($part) ? $part : $this->normalizeMimePart($part);
            if ($normalized === null) {
                continue;
            }

            $type        = $normalized['type'];
            $isAttached  = strpos($normalized['disposition'], 'attachment') !== false
                || $normalized['filename'] !== '';

            if (!$isAttached && strpos($type, 'multipart/') === 0) {
                $this->collectMimeParts($this->splitMultipart($normalized), $collected, $depth + 1);
                continue;
            }

            if (!$isAttached && strpos($type, 'text/html') === 0) {
                $collected['html'] = $normalized['content'];
                continue;
            }

            if (!$isAttached && strpos($type, 'text/plain') === 0) {
                if ($collected['plain'] === '') {
                    $collected['plain'] = $normalized['content'];
                }
                continue;
            }

            $collected['attachments'][] = [
                'filename'    => $normalized['filename'] ?: 'attachment',
                'mime_type'   => $type ?: 'application/octet-stream',
                'disposition' => $normalized['disposition'] ?: 'attachment',
                'content'     => $normalized['content'],
            ];
        }
    }

    /**
     * Normalize a MIME part (Laminas or Magento) into a plain array shape.
     *
     * @param mixed $part
     *
     * @return array|null
     */
    protected function normalizeMimePart($part)
    {
        if (!$part instanceof MimePart && !$part instanceof MagentoMimePart) {
            return null;
        }

        return [
            'type'        => strtolower((string) $part->getType()),
            'disposition' => strtolower((string) $part->getDisposition()),
            'filename'    => $this->readPartFileName($part),
            'content'     => $part->getRawContent(),
        ];
    }

    /**
     * Split a multipart MIME part into its child parts.
     *
     * @param array $part
     *
     * @return array
     */
    protected function splitMultipart(array $part)
    {
        if (!preg_match('#boundary\s*=\s*"?([^";\s]+)"?#i', $part['type'], $matches)) {
            return [];
        }

        try {
            $struct = Decode::splitMessageStruct($part['content'], $matches[1]);
        } catch (\Throwable $e) {
            return [];
        }

        $children = [];
        foreach ((array) $struct as $entry) {
            $headers = $entry['header'] ?? null;
            $value   = static function ($name) use ($headers) {
                try {
                    return ($headers && $headers->has($name)) ? (string) $headers->get($name)->getFieldValue() : '';
                } catch (\Throwable $e) {
                    return '';
                }
            };

            $disposition = $value('Content-Disposition');
            $filename    = '';
            if (preg_match('#filename\s*=\s*"?([^";]+)"?#i', $disposition, $nameMatch)) {
                $filename = trim($nameMatch[1]);
            }

            $children[] = [
                'type'        => strtolower($value('Content-Type')) ?: 'text/plain',
                'disposition' => strtolower($disposition),
                'filename'    => $filename,
                'content'     => $this->decodePartBody(
                    (string) ($entry['body'] ?? ''),
                    $value('Content-Transfer-Encoding')
                ),
            ];
        }

        return $children;
    }

    /**
     * Decode a MIME part body according to its Content-Transfer-Encoding.
     *
     * @param string $body
     * @param string $encoding
     *
     * @return string
     */
    protected function decodePartBody($body, $encoding)
    {
        $encoding = strtolower(trim($encoding));

        if ($encoding === 'base64') {
            return (string) base64_decode($body, true);
        }

        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }

        return $body;
    }

    /**
     * Read a MIME part's filename, swallowing any error.
     *
     * @param mixed $part
     *
     * @return string
     */
    protected function readPartFileName($part)
    {
        try {
            return (string) $part->getFileName();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Extract attachments from a Symfony mailer message.
     *
     * @param mixed $message
     *
     * @return array
     */
    protected function extractSymfonyAttachments($message)
    {
        $attachments = [];

        if (!method_exists($message, 'getAttachments')) {
            return $attachments;
        }

        try {
            $parts = $message->getAttachments();
        } catch (\Throwable $e) {
            return $attachments;
        }

        foreach ($parts as $part) {
            $attachments[] = [
                'filename'    => method_exists($part, 'getFilename') ? $part->getFilename() : 'attachment',
                'mime_type'   => method_exists($part, 'getMediaType')
                    ? $part->getMediaType() . '/' . $part->getMediaSubtype()
                    : 'application/octet-stream',
                'disposition' => method_exists($part, 'getDisposition')
                    ? (string) $part->getDisposition()
                    : 'attachment',
                'content'     => $part->getBody(),
            ];
        }

        return $attachments;
    }

    /**
     * Persist any pending attachments collected during saveLog()/saveLogSymfony().
     *
     * @return void
     */
    protected function saveAttachments()
    {
        if (!$this->pendingAttachments || !$this->getId()) {
            $this->pendingAttachments = [];

            return;
        }

        foreach ($this->pendingAttachments as $attachment) {
            try {
                $this->getAttachmentFactory()->create()
                    ->addData($attachment)
                    ->setLogId($this->getId())
                    ->save();
            } catch (Exception $e) {
                $this->_logger->critical($e->getMessage());
            }
        }

        $this->pendingAttachments = [];
    }

    /**
     * Get the attachments saved for this log entry.
     *
     * @return array
     */
    public function getAttachments()
    {
        if (!$this->getId()) {
            return [];
        }

        return $this->getAttachmentCollectionFactory()->create()
            ->addFieldToFilter('log_id', $this->getId())
            ->getItems();
    }

    /**
     * Apply optional extra columns (error_message, entity_type, entity_id) onto this log entry.
     *
     * @param array $extra
     */
    protected function applyExtraData(array $extra)
    {
        if (array_key_exists('error_message', $extra) && $extra['error_message'] !== null) {
            $this->setErrorMessage($extra['error_message']);
        }

        if (array_key_exists('entity_type', $extra) && $extra['entity_type'] !== null) {
            $this->setEntityType($extra['entity_type']);
        }

        if (array_key_exists('entity_id', $extra) && $extra['entity_id'] !== null) {
            $this->setEntityId($extra['entity_id']);
        }
    }

    /**
     * Resend a previously logged email using its stored sender/recipients/content.
     *
     * @return bool
     */
    public function resendEmail()
    {
        $data                  = $this->getData();
        $data['email_content'] = htmlspecialchars_decode((string) ($data['email_content'] ?? ''));

        $dataObject = new DataObject();
        $dataObject->setData($data);

        $sender = $this->extractEmailInfo($data['sender']);
        foreach ($sender as $name => $email) {
            $sender = compact('name', 'email');
            break;
        }

        /** Add receiver emails*/
        $recipient = $this->extractEmailInfo($data['recipient']);
        foreach ($recipient as $name => $email) {
            if ($this->helper->versionCompare('2.2.8')) {
                $this->_transportBuilder->addTo($email);
            } else {
                $this->_transportBuilder->addTo($email, $name);
            }
        }

        /** Add cc emails*/
        if (isset($data['cc'])) {
            $ccEmails = $this->extractEmailInfo($data['cc']);
            foreach ($ccEmails as $email) {
                $this->_transportBuilder->addCc($email);
            }
        }

        /** Add Bcc emails*/
        if (isset($data['bcc'])) {
            $bccEmails = $this->extractEmailInfo($data['bcc']);
            foreach ($bccEmails as $email) {
                $this->_transportBuilder->addBcc($email);
            }
        }

        $this->mailResource->setSmtpOptions(Store::DEFAULT_STORE_ID, ['force_sent' => true]);

        try {
            $this->_transportBuilder
                ->setTemplateIdentifier('mpsmtp_resend_email_template')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars($data)
                ->setFrom($sender);

            $transport = $this->_transportBuilder->getTransport();
            $this->reattachFiles($transport);
            $transport->sendMessage();

            // Do not flip/save this row: sendMessage() above already goes through the module's
            // own Transport plugin, which logs the resend as its own new row. Also mutating the
            // original row to SUCCESS here used to make the grid show two identical-looking rows
            // for a single resend.
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());

            return false;
        }

        $this->flagLinkedEntityAsEmailed();

        return true;
    }

    /**
     * SMTP-4: a resent email may belong to an order/invoice/shipment/creditmemo whose
     * confirmation email originally failed -- entity_type/entity_id (set by
     * Mageplaza\Smtp\Observer\Email\SetTemplateVarsEntity at send time) link this log row
     * back to it. Flip its email_sent flag so admin no longer sees the "not sent" banner.
     * A failure here must not turn an already-successful resend into a failure.
     */
    protected function flagLinkedEntityAsEmailed()
    {
        $entityType = $this->getEntityType();
        $entityId   = $this->getEntityId();

        if (!$entityType || !$entityId) {
            return;
        }

        try {
            $this->getEmailSentFlagUpdater()->updateEmailSent($entityType, $entityId);
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
    }

    /**
     * Re-attach this log entry's saved attachments onto the outgoing transport's message.
     *
     * @param mixed $transport
     *
     * @return void
     */
    protected function reattachFiles($transport)
    {
        $attachments = $this->getAttachments();
        if (!$attachments || !method_exists($transport, 'getMessage')) {
            return;
        }

        try {
            $message = $transport->getMessage();

            if (method_exists($message, 'getSymfonyMessage')) {
                $this->attachToSymfonyMessage($message->getSymfonyMessage(), $attachments);

                return;
            }

            $body = $message->getBody();

            if (!$body instanceof MimeMessage && !$body instanceof MagentoMimeMessage) {
                return;
            }

            $parts = $body->getParts();

            foreach ($attachments as $attachment) {
                $part = new MimePart($attachment->getContent());
                $part->type        = $attachment->getMimeType() ?: 'application/octet-stream';
                $part->encoding    = Mime::ENCODING_BASE64;
                $part->disposition = Mime::DISPOSITION_ATTACHMENT;
                $part->filename    = $attachment->getFilename() ?: 'attachment';

                $parts[] = $part;
            }

            $body->setParts($parts);
            $message->setBody($body);
        } catch (\Throwable $e) {
            // A resend without its files still beats no resend at all.
            $this->_logger->critical($e->getMessage());
        }
    }

    /**
     * Attach saved attachments onto a Symfony mailer message body.
     *
     * @param mixed $symfonyMessage
     * @param array $attachments
     *
     * @return void
     */
    protected function attachToSymfonyMessage($symfonyMessage, array $attachments)
    {
        if (!$symfonyMessage || !method_exists($symfonyMessage, 'getBody')) {
            return;
        }

        $body = $symfonyMessage->getBody();
        if (!$body instanceof AbstractPart) {
            return;
        }

        $parts = [];
        foreach ($attachments as $attachment) {
            $parts[] = new DataPart(
                (string) $attachment->getContent(),
                $attachment->getFilename() ?: 'attachment',
                $attachment->getMimeType() ?: 'application/octet-stream'
            );
        }

        if (!$parts) {
            return;
        }

        $symfonyMessage->setBody(new MixedPart($body, ...$parts));
    }

    /**
     * Parse a "Name <email>" or comma-separated email string into a [name => email] array.
     *
     * @param mixed $emailList
     *
     * @return array
     */
    protected function extractEmailInfo($emailList)
    {
        $data = [];

        if ($this->helper->versionCompare('2.2.8')) {
            $emailList = preg_replace('/\s+/', '', $emailList);
            if (strpos($emailList, '<') !== false) {
                $emails = explode('<', $emailList);
                $name   = '';
                if (count($emails) > 1) {
                    $name = $emails[0];
                }
                $email       = trim($emails[1], '>');
                $data[$name] = $email;
            } else {
                $emails = explode(',', $emailList);
                foreach ($emails as $email) {
                    $data[] = $email;
                }
            }
        } else {
            $emails = explode(', ', $emailList);
            foreach ($emails as $email) {
                if (strpos($emailList, ' <') !== false) {
                    $emailArray = explode(' <', $email);
                    $name       = '';
                    if (count($emailArray) > 1) {
                        $name  = trim($emailArray[0], '" ');
                        $email = trim($emailArray[1], '<>');
                    }
                    $data[$name] = $email;
                }
            }
        }

        return $data;
    }
}
