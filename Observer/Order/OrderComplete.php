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
use Mageplaza\Smtp\Helper\EmailMarketing;
use Psr\Log\LoggerInterface;

/**
 * Class OrderComplete
 * @package Mageplaza\Smtp\Observer\Order
 */
class OrderComplete implements ObserverInterface
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
     * @var ResourceOrder
     */
    protected $resourceOrder;

    /**
     * OrderComplete constructor.
     *
     * @param EmailMarketing $helperEmailMarketing
     * @param LoggerInterface $logger
     * @param ResourceOrder $resourceOrder
     */
    public function __construct(
        EmailMarketing $helperEmailMarketing,
        LoggerInterface $logger,
        ResourceOrder $resourceOrder
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->logger               = $logger;
        $this->resourceOrder        = $resourceOrder;
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
            try {
                /* @var Order $order */
                $order    = $observer->getEvent()->getOrder();
                if (!$order->getData('mp_smtp_email_marketing_order_created') &&
                    $order->getCreatedAt() === $order->getUpdatedAt()
                ) {
                    $this->syncOrder($order);
                    $this->helperEmailMarketing->updateCustomer($order->getCustomerId());
                    $order->setData('mp_smtp_email_marketing_order_created', true);
                    $this->updateFlag($order->getId(), 'mp_smtp_email_marketing_order_created');

                } else {
                    if (!in_array($order->getState(), [Order::STATE_NEW, Order::STATE_COMPLETE], true)) {
                        $data = [
                            'id'         => $order->getId(),
                            'status'     => $order->getStatus(),
                            'state'      => $order->getState(),
                            'email'      => $order->getCustomerEmail(),
                            'is_utc'     => true,
                            'created_at' => $this->helperEmailMarketing->formatDate($order->getCreatedAt()),
                            'updated_at' => $this->helperEmailMarketing->formatDate($order->getUpdatedAt())
                        ];
                        $this->helperEmailMarketing->updateOrderStatusRequest($data);
                    }
                }

                $isSynced = $order->getData('mp_smtp_email_marketing_synced');

                if ($order->getState() === Order::STATE_COMPLETE &&
                    !$isSynced) {
                    $this->helperEmailMarketing->sendOrderRequest($order, EmailMarketing::ORDER_COMPLETE_URL);
                    $this->updateFlag($order->getId(), 'mp_smtp_email_marketing_synced');
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param Order $order
     */
    public function syncOrder($order)
    {
        try {
            $this->helperEmailMarketing->sendOrderRequest($order);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * @param int|string $orderId
     * @param string $field
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateFlag($orderId, $field)
    {
        $resource = $this->resourceOrder;
        $resource->getConnection()->update(
            $resource->getMainTable(),
            [$field => 1],
            ['entity_id = ?' => $orderId]
        );
    }
}
