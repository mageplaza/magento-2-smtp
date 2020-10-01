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

namespace Mageplaza\Smtp\Observer\Customer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Customer\Model\Customer;
use Psr\Log\LoggerInterface;

/**
 * Class CustomerSaveCommitAfter
 * @package Mageplaza\Smtp\Observer\Customer
 */
class CustomerSaveCommitAfter implements ObserverInterface
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
     * CustomerSaveCommitAfter constructor.
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
     */
    public function execute(Observer $observer)
    {
        /**
         * @var Customer $customer
         */
        $customer = $observer->getEvent()->getDataObject();
        if ($this->helperEmailMarketing->isEnableAbandonedCart() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID() &&
            $customer->getIsNewRecord()
        ) {
            try {
                $data = $this->helperEmailMarketing->getCustomerData($customer);

                $this->helperEmailMarketing->syncCustomer($data);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
