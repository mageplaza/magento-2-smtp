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
        $this->logger = $logger;
        $this->resourceOrder = $resourceOrder;
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
                $order = $observer->getEvent()->getOrder();
                $isSynced = $order->getData('mp_smtp_email_marketing_synced');
                if (!in_array($order->getState(), [Order::STATE_NEW, Order::STATE_COMPLETE], true)) {
                    $data = [
                        'id'     => $order->getId(),
                        'status' => $order->getStatus(),
                        'state'  => $order->getState(),
                        'email'  => $order->getCustomerEmail()
                    ];
                    $this->helperEmailMarketing->updateOrderStatusRequest($data);
                }

                if ($order->getState() === Order::STATE_COMPLETE &&
                    !$isSynced) {
                    $this->helperEmailMarketing->sendOrderRequest($order, EmailMarketing::ORDER_COMPLETE_URL);
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
