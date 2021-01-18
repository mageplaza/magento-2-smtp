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

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\Smtp\Helper\EmailMarketing;

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
     * InvoiceCommitAfter constructor.
     *
     * @param EmailMarketing $helperEmailMarketing
     */
    public function __construct(
        EmailMarketing $helperEmailMarketing
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
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
            /* @var Invoice $order */
            $invoice = $observer->getEvent()->getDataObject();

            if ($invoice->getId() && $invoice->getCreatedAt() == $invoice->getUpdatedAt()) {
                $this->helperEmailMarketing->sendOrderRequest($invoice, EmailMarketing::INVOICE_URL);
            }
             $this->helperEmailMarketing->updateCustomer($invoice->getOrder()->getCustomerId());
        }
    }
}
