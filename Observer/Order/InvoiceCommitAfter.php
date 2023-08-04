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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Sales\Model\ResourceModel\Order as ResourceOrder;
use Psr\Log\LoggerInterface;

/**
 * Class InvoiceCommitAfter
 * @package Mageplaza\Smtp\Observer\Order
 */
class InvoiceCommitAfter implements ObserverInterface
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
     * InvoiceCommitAfter constructor.
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
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            /* @var Invoice $order */
            $invoice = $observer->getEvent()->getDataObject();
            $order   = $invoice->getOrder();

            try {
                if (!$order->getData('mp_smtp_email_marketing_order_created') &&
                    $order->getCreatedAt() === $order->getUpdatedAt()) {
                    $this->helperEmailMarketing->sendOrderRequest($order);
                    $this->helperEmailMarketing->updateCustomer($order->getCustomerId());
                    $order->setData('mp_smtp_email_marketing_order_created', true);
                    $resource = $this->resourceOrder;
                    $resource->getConnection()->update(
                        $resource->getMainTable(),
                        ['mp_smtp_email_marketing_order_created' => 1],
                        ['entity_id = ?' => $order->getId()]
                    );
                }

                if ($invoice->getId() && $invoice->getCreatedAt() == $invoice->getUpdatedAt()) {
                    $this->helperEmailMarketing->sendOrderRequest($invoice, EmailMarketing::INVOICE_URL);
                }

                $this->helperEmailMarketing->updateCustomer($order->getCustomerId());

            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
