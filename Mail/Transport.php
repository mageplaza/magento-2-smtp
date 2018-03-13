<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Mail;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\LogFactory;

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
     * @var \Mageplaza\Smtp\Mail\Rse\Mail
     */
    protected $resourceMail;

    /**
     * @var \Mageplaza\Smtp\Model\LogFactory
     */
    protected $logFactory;

    /**
     * @var \Magento\Framework\Registry $registry
     */
    protected $registry;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * Transport constructor.
     * @param Mail $resourceMail
     * @param LogFactory $logFactory
     * @param Registry $registry
     * @param Data $helper
     */
    public function __construct(
        Mail $resourceMail,
        LogFactory $logFactory,
        Registry $registry,
        Data $helper
    )
    {
        $this->resourceMail = $resourceMail;
        $this->logFactory = $logFactory;
        $this->registry = $registry;
        $this->helper = $helper;
    }

    /**
     * @param TransportInterface $subject
     * @param \Closure $proceed
     * @throws \Exception
     *
     * @return null
     */
    public function aroundSendMessage(
        TransportInterface $subject,
        \Closure $proceed
    )
    {
        $this->_storeId = $this->registry->registry('mp_smtp_store_id');
        $message = $this->getMessage($subject);
        if ($this->resourceMail->isModuleEnable($this->_storeId) && $message) {
            $message = $this->resourceMail->processMessage($message, $this->_storeId);
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
            $proceed();
        }
    }

    /**
     * @param $transport
     * @return mixed|null
     */
    protected function getMessage($transport)
    {
        if ($this->helper->versionCompare('2.2.0')) {
            return $transport->getMessage();
        }

        try {
            $reflectionClass = new \ReflectionClass($transport);
            $message = $reflectionClass->getProperty('_message');
            $message->setAccessible(true);

            return $message->getValue($transport);
        } catch (\Exception $e) {
            return null;
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
}
