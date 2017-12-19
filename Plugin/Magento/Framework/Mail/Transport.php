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

namespace Mageplaza\Smtp\Plugin\Magento\Framework\Mail;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Mageplaza\Smtp\Application\Rse\Mail;
use Mageplaza\Smtp\Model\LogFactory;

/**
 * Class Transport
 * @package Mageplaza\Smtp\Plugin\Magento\Framework\Mail
 */
class Transport
{
    /**
     * @var \Mageplaza\Smtp\Model\LogFactory
     */
    protected $logFactory;

    /**
     * @var \Mageplaza\Smtp\Application\Rse\Mail
     */
    protected $resourceMail;

    /**
     * Transport constructor.
     * @param \Mageplaza\Smtp\Application\Rse\Mail $resourceMail
     * @param \Mageplaza\Smtp\Model\LogFactory $logFactory
     */
    public function __construct(
        Mail $resourceMail,
        LogFactory $logFactory
    )
    {
        $this->resourceMail = $resourceMail;
        $this->logFactory   = $logFactory;
    }

    /**
     * @param \Magento\Framework\Mail\TransportInterface $subject
     * @param \Closure $proceed
     * @throws \Magento\Framework\Exception\MailException
     */
    public function aroundSendMessage(TransportInterface $subject, \Closure $proceed)
    {
        if ($this->resourceMail->isModuleEnable()) {
            $message   = $this->resourceMail->getMessage();
            $transport = $this->resourceMail->init();
            try {
                if (!$this->resourceMail->isDeveloperMode()) {
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
        if ($this->resourceMail->isEnableEmailLog()) {
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
