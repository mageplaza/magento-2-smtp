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

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Zend_Db_Expr;

/**
 * Class Customer
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class Customer extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * Customer constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param CustomerFactory $customerFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        CustomerFactory $customerFactory,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->customerFactory = $customerFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $result = [];
        try {
            $attribute = $this->helperEmailMarketing->getSyncedAttribute();

            $customerCollection = $this->customerCollectionFactory->create();
            $ids = $this->getRequest()->getParam('ids');
            $subscriberTable = $customerCollection->getTable('newsletter_subscriber');
            $customerCollection->getSelect()->columns(
                [
                    'subscriber_status' => new Zend_Db_Expr(
                        '(SELECT `s`.`subscriber_status` FROM `' . $subscriberTable . '` as `s` WHERE `s`.`customer_id` = `e`.`entity_id` LIMIT 1)'
                    )
                ]
            );

            $customers = $customerCollection->addFieldToFilter('entity_id', ['in' => $ids]);

            $data = [];
            $attributeData = [];
            foreach ($customers as $customer) {
                $data[] = $this->helperEmailMarketing->getCustomerData($customer, false, true);
                $attributeData[] = [
                    'attribute_id' => $attribute->getId(),
                    'entity_id' => $customer->getId(),
                    'value' => 1
                ];
            }

            $result['status'] = true;
            $result['total'] = count($ids);
            $response = $this->helperEmailMarketing->syncCustomers($data);
            if (isset($response['success'])) {
                $table = $customerCollection->getTable('customer_entity_int');
                $this->insertData($customerCollection->getConnection(), $attributeData, $table);
            }

        } catch (Exception $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }

    /**
     * @param AdapterInterface $connection
     * @param array $data
     * @param string $table
     *
     * @throws Exception
     */
    public function insertData($connection, $data, $table)
    {
        $connection->beginTransaction();
        try {
            $connection->insertMultiple($table, $data);
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
