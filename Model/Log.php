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
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\Source\Status;

/**
 * Class Log
 * @package Mageplaza\Smtp\Model
 */
class Log extends AbstractModel
{
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
     */
    public function __construct(
        Context $context,
        Registry $registry,
        TransportBuilder $transportBuilder,
        Mail $mailResource,
        Data $helper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->_transportBuilder = $transportBuilder;
        $this->mailResource      = $mailResource;
        $this->helper            = $helper;
    }

    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourceModel\Log::class);
    }

    /**
     * Save email logs
     *
     * @param $message
     * @param $status
     */
    public function saveLog($message, $status)
    {
        if ($this->helper->versionCompare('2.2.8')) {
            if ($message->getSubject()) {
                $this->setSubject($message->getSubject());
            }

            $from = $message->getFrom();
            if (count($from)) {
                $from->rewind();
                $this->setSender($from->current()->getName() . ' <' . $from->current()->getEmail() . '>');
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

            if ($this->helper->versionCompare('2.3.3')) {
                $messageBody = quoted_printable_decode($message->getBodyText());
                $content     = htmlspecialchars($messageBody);
            } else {
                $content = htmlspecialchars($message->getBodyText());
            }
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
            ->save();
    }

    /**
     * @return bool
     */
    public function resendEmail()
    {
        $data                  = $this->getData();
        $data['email_content'] = htmlspecialchars_decode($data['email_content']);

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

        $this->mailResource->setSmtpOptions(Store::DEFAULT_STORE_ID, ['ignore_log' => true]);

        try {
            $this->_transportBuilder
                ->setTemplateIdentifier('mpsmtp_resend_email_template')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars($data)
                ->setFrom($sender);

            $this->_transportBuilder->getTransport()
                ->sendMessage();

            $this->setStatus(Status::STATUS_SUCCESS)
                ->save();
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param $emailList
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
