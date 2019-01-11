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

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\LogFactory;
use Psr\Log\LoggerInterface;
use Zend\Mail\Message;

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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Transport constructor.
     * @param Mail $resourceMail
     * @param LogFactory $logFactory
     * @param Registry $registry
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Mail $resourceMail,
        LogFactory $logFactory,
        Registry $registry,
        Data $helper,
        LoggerInterface $logger
    )
    {
        $this->resourceMail = $resourceMail;
        $this->logFactory = $logFactory;
        $this->registry = $registry;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @param TransportInterface $subject
     * @param \Closure $proceed
     * @throws MailException
     * @throws \ReflectionException
     */
    public function aroundSendMessage(
        TransportInterface $subject,
        \Closure $proceed
    )
    {
        $this->_storeId = $this->registry->registry('mp_smtp_store_id');
        $message = $this->getMessage($subject);
        if ($this->resourceMail->isModuleEnable($this->_storeId) && $message) {
            try {
                if (!$this->resourceMail->isDeveloperMode($this->_storeId)) {
                    if ($message instanceof \Zend_Mail) {
                        try {
                            $message = $this->resourceMail->processMessage($message, $this->_storeId);
                            $transport = $this->resourceMail->getTransportZend($this->_storeId);
                            #For magento 2.2.7
                            if ((bool)array_key_exists("From", $message->getHeaders()) == false) {
                                $email = $this->registry->registry("test");
                                $message->setFrom($email["email"], $email["name"]);
                            }
                            $transport->send($message, $this->_storeId);
                        } catch (\Exception $e) {
                            throw new \Magento\Framework\Exception\MailException(new \Magento\Framework\Phrase($e->getMessage()), $e);
                        }
                    } elseif ($message instanceof \Magento\Framework\Mail\Message) {
                        try {
                            $test = Message::fromString($message->getRawMessage());
                            $transportNewVersion = $this->resourceMail->getTransportZendNewVersion($this->_storeId);
                            if ($test->getFrom()->count() == 0) {
                                $email = $this->registry->registry("test");
                                $test->setFrom($email["email"], $email["name"]);
                            }
                            $transportNewVersion->send(
                                $test,
                                $this->_storeId
                            );
                        } catch (\Exception $e) {
                            throw new \Magento\Framework\Exception\MailException(new \Magento\Framework\Phrase($e->getMessage()), $e);
                        }
                    }
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
     * @return mixed|null|\ReflectionProperty
     * @throws \ReflectionException
     */
    protected function getMessage($transport)
    {
        if ($this->helper->versionCompare('2.2.0')) {
            if (method_exists($transport, 'getMessage')) {
                $message = $transport->getMessage();
            } else {
                $message = $this->useReflectionToGetMessage($transport);
            }

            return $message;
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
     */
    protected function emailLog($message, $status = true)
    {
        if ($this->resourceMail->isEnableEmailLog($this->_storeId)) {
            /** @var \Mageplaza\Smtp\Model\Log $log */
            $log = $this->logFactory->create();
            try {
                if ($message instanceof \Zend_Mail) {
                    #case process zend
                    $log->saveLog($message, $status);
                } else {
                    #case process zend new version
                    $message = Message::fromString($message->getRawMessage());
                    if ($message->getFrom()->count() == 0) {
                        $email = $this->registry->registry("test");
                        $message->setFrom($email["email"], $email["name"]);
                    }
                    $log->saveLogNewVersion($message, $status);
                }
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param $subject
     * @return mixed
     * @throws \ReflectionException
     */
    protected function useReflectionToGetMessage($subject)
    {
        $reflection = new \ReflectionClass($subject);
        $property = $reflection->getProperty('_message');
        $property->setAccessible(true);
        $message = $property->getValue($subject);

        return $message;
    }
}
