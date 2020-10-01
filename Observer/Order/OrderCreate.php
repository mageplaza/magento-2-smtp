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
use Magento\Sales\Model\Order;
use Mageplaza\Smtp\Helper\AbandonedCart;
use Psr\Log\LoggerInterface;

/**
 * Class OrderCreate
 * @package Mageplaza\Smtp\Observer\Order
 */
class OrderCreate implements ObserverInterface
{
    /**
     * @var AbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * OrderCreate constructor.
     *
     * @param AbandonedCart $helperAbandonedCart
     * @param LoggerInterface $logger
     */
    public function __construct(
        AbandonedCart $helperAbandonedCart,
        LoggerInterface $logger
    ) {
        $this->helperAbandonedCart = $helperAbandonedCart;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {

        if ($this->helperAbandonedCart->isEnableAbandonedCart() &&
            $this->helperAbandonedCart->getSecretKey() &&
            $this->helperAbandonedCart->getAppID()
        ) {
            try {
                /* @var Order $order */
                $order = $observer->getEvent()->getOrder();
                if ($order->getId()) {
                    $this->helperAbandonedCart->sendOrderRequest($order);
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
