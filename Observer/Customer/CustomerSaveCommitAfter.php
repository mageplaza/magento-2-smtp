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
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as ResourceCustomer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
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
     * @var ResourceCustomer
     */
    protected $resourceCustomer;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerSaveCommitAfter constructor.
     *
     * @param EmailMarketing $helperEmailMarketing
     * @param LoggerInterface $logger
     * @param ResourceCustomer $resourceCustomer
     */
    public function __construct(
        EmailMarketing $helperEmailMarketing,
        LoggerInterface $logger,
        ResourceCustomer $resourceCustomer
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->logger = $logger;
        $this->resourceCustomer = $resourceCustomer;
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
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            try {
                $isLoadSubscriber = !$customer->getIsNewRecord();
                $data = $this->helperEmailMarketing->getCustomerData($customer, $isLoadSubscriber);

                if ($customer->getIsNewRecord()) {
                    $result = $this->helperEmailMarketing->syncCustomer($data);
                    if (!empty($result['success'])) {
                        $this->helperEmailMarketing->setIsSyncedCustomer(true);
                        $table = $this->resourceCustomer->getTable('customer_entity_int');
                        $connection = $this->resourceCustomer->getConnection();
                        $attribute = $this->helperEmailMarketing->getSyncedAttribute();
                        $data = [
                            'attribute_id' => $attribute->getId(),
                            'entity_id' => $customer->getId(),
                            'value' => 1
                        ];
                        $connection->insert($table, $data);
                    }
                } else {
                    $origData = $this->helperEmailMarketing->getCustomerData(
                        $customer->getCustomOrigObject(),
                        $isLoadSubscriber
                    );
                    if ($origData !== $data) {
                        $this->helperEmailMarketing->syncCustomer($data, false);
                    }
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
