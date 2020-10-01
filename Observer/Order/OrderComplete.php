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
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;
use Mageplaza\Smtp\Helper\AbandonedCart;
use Psr\Log\LoggerInterface;

/**
 * Class OrderComplete
 * @package Mageplaza\Smtp\Observer\Order
 */
class OrderComplete implements ObserverInterface
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
     * @var ResourceOrder
     */
    protected $resourceOrder;

    /**
     * OrderComplete constructor.
     *
     * @param AbandonedCart $helperAbandonedCart
     * @param LoggerInterface $logger
     * @param ResourceOrder $resourceOrder
     */
    public function __construct(
        AbandonedCart $helperAbandonedCart,
        LoggerInterface $logger,
        ResourceOrder $resourceOrder
    ) {
        $this->helperAbandonedCart = $helperAbandonedCart;
        $this->logger = $logger;
        $this->resourceOrder = $resourceOrder;
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
                if ($order->getState() === Order::STATE_COMPLETE &&
                    !$order->getData('mp_smtp_email_marketing_synced')) {
                    $this->helperAbandonedCart->sendOrderRequest($order, 'orders/complete');
                    $resource = $this->resourceOrder;
                    $resource->getConnection()->update(
                        $resource->getMainTable(),
                        ['mp_smtp_email_marketing_synced' => 1],
                        ['entity_id = ?' => $order->getId()]
                    );
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
