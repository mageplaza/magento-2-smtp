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
use Magento\Framework\Phrase;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\LogFactory;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Registry;

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
     * Transport constructor.
     * @param \Mageplaza\Smtp\Mail\Rse\Mail $resourceMail
     * @param \Mageplaza\Smtp\Model\LogFactory $logFactory
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        Mail $resourceMail,
        LogFactory $logFactory,
        Registry $registry
    )
    {
        $this->resourceMail = $resourceMail;
        $this->logFactory = $logFactory;
        $this->registry = $registry;
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
        //Dotmailer
        if (method_exists($subject, 'getMessage')) {
            $this->_message = $subject->getMessage();
        } else {
            //For < 2.2
            $reflection = new \ReflectionClass($subject);
            $property = $reflection->getProperty('_message');
            $property->setAccessible(true);
            $this->_message = $property->getValue($subject);
        }
        //end Dotmailer
        if ($this->resourceMail->isModuleEnable($this->_storeId)) {
            $message = $this->resourceMail->processMessage($this->_message, $this->_storeId);
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
