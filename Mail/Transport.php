<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) 2017 Mageplaza (https://www.mageplaza.com/)
 * @license     http://mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Mail;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Phrase;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\LogFactory;

/**
 * Class Transport
 * @package Mageplaza\Smtp\Mail
 */
class Transport extends \Magento\Framework\Mail\Transport
{
    /**
     * @var int Store Id
     */
    protected $_storeId;

    /**
     * @var \Mageplaza\Smtp\Mail\Rse\Mail
     */
    protected $resourceMail;

    /**
     * @var \Mageplaza\Smtp\Model\LogFactory
     */
    protected $logFactory;

    /**
     * Transport constructor.
     * @param \Magento\Framework\Mail\MessageInterface $message
     * @param \Mageplaza\Smtp\Mail\Rse\Mail $resourceMail
     * @param \Mageplaza\Smtp\Model\LogFactory $logFactory
     * @param null $parameters
     */
    public function __construct(
        MessageInterface $message,
        Mail $resourceMail,
        LogFactory $logFactory,
        $parameters = null
    )
    {
        parent::__construct($message, $parameters);

        $this->resourceMail = $resourceMail;
        $this->logFactory   = $logFactory;
    }

    /**
     * Send a mail using this transport
     *
     * @return void
     * @throws \Magento\Framework\Exception\MailException
     */
    public function sendMessage()
    {
        if ($this->resourceMail->isModuleEnable($this->_storeId)) {
            $message   = $this->resourceMail->processMessage($this->_message, $this->_storeId);
            $transport = $this->resourceMail->getTransport($this->_storeId);
            try {
                if (!$this->resourceMail->isDeveloperMode($this->_storeId)) {
                    $transport->send($message);
                }
                $this->emailLog($message);
            } catch (\Exception $e) {
                $this->emailLog($message, false);
                throw new MailException(new Phrase($e->getMessage()), $e);
            }
        } else {
            parent::sendMessage();
        }
    }

    /**
     * Save Email Sent
     *
     * @param $message
     * @param bool $status
     * @throws \Magento\Framework\Exception\MailException
     */
    protected function emailLog($message, $status = true)
    {
        if ($this->resourceMail->isEnableEmailLog($this->_storeId)) {
            /** @var \Mageplaza\Smtp\Model\Log $log */
            $log = $this->logFactory->create();
            try {
                $log->saveLog($message, $status);
            } catch (\Exception $e) {
                throw new MailException(new Phrase($e->getMessage()), $e);
            }
        }
    }

    /**
     * @param $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = $storeId;

        return $this;
    }
}
