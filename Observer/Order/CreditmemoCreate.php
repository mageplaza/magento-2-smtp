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

namespace Mageplaza\Smtp\Observer\Order;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Psr\Log\LoggerInterface;

/**
 * Class CreditmemoCreate
 * @package Mageplaza\Smtp\Observer\Order
 */
class CreditmemoCreate implements ObserverInterface
{
    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CreditmemoCreate constructor.
     *
     * @param EmailMarketing $helperEmailMarketing
     * @param LoggerInterface $logger
     */
    public function __construct(
        EmailMarketing $helperEmailMarketing,
        LoggerInterface $logger
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            /* @var Creditmemo $creditmemo */
            $creditmemo = $observer->getEvent()->getDataObject();
            $this->syncCreditmemo($creditmemo);
            $this->helperEmailMarketing->updateCustomer($creditmemo->getOrder()->getCustomerId());
        }
    }

    /**
     * @param Creditmemo $creditmemo
     */
    public function syncCreditmemo($creditmemo)
    {
        try {
            if ($creditmemo->getId() && $creditmemo->getCreatedAt() == $creditmemo->getUpdatedAt()) {
                $this->helperEmailMarketing->sendOrderRequest($creditmemo, EmailMarketing::CREDITMEMO_URL);
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
