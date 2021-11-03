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
     * InvoiceCommitAfter constructor.
     *
     * @param EmailMarketing $helperEmailMarketing
     * @param LoggerInterface $logger
     */
    public function __construct(
        EmailMarketing $helperEmailMarketing,
        LoggerInterface $logger
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->logger               = $logger;
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
                if (!$order->getData('mp_em_flag_create_order') && $order->getCreatedAt() === $order->getUpdatedAt()) {
                    $this->helperEmailMarketing->sendOrderRequest($order);
                    $this->helperEmailMarketing->updateCustomer($order->getCustomerId());
                    $order->setData('mp_em_flag_create_order', true);
                }

                if ($invoice->getId() && $invoice->getCreatedAt() == $invoice->getUpdatedAt()) {
                    $this->helperEmailMarketing->sendOrderRequest($invoice, EmailMarketing::INVOICE_URL);
                }

                $this->helperEmailMarketing->updateCustomer($invoice->getOrder()->getCustomerId());

            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
