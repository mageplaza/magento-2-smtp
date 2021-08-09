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
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
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
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::email_marketing';

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * Customer constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->helperEmailMarketing      = $helperEmailMarketing;
        $this->customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $daysRange = $this->getRequest()->getParam('days_range');
        $from      = $this->getRequest()->getParam('from');
        $to        = $this->getRequest()->getParam('to');
        $result    = [];

        try {
            $attribute          = $this->helperEmailMarketing->getSyncedAttribute();
            $customerCollection = $this->customerCollectionFactory->create();
            $ids                = $this->getRequest()->getParam('ids');
            $subscriberTable    = $customerCollection->getTable('newsletter_subscriber');
            $customerCollection->getSelect()->columns(
                [
                    'subscriber_status' => new Zend_Db_Expr(
                        '(SELECT `s`.`subscriber_status` FROM `' . $subscriberTable . '` as `s` WHERE `s`.`customer_id` = `e`.`entity_id` LIMIT 1)'
                    )
                ]
            );

            $customers = $customerCollection->addFieldToFilter('entity_id', ['in' => $ids]);

            if ($this->helperEmailMarketing->isOnlyNotSync()) {
                $customers->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
            }

            if ($query = $this->helperEmailMarketing->queryExpr($daysRange, $from, $to, 'e')) {
                $customerCollection->getSelect()->where($query);
            }

            $data          = [];
            $attributeData = [];
            $idUpdate      = [];

            foreach ($customers as $customer) {
                $data[]          = $this->helperEmailMarketing->getCustomerData($customer, false, true);
                $attributeData[] = [
                    'attribute_id' => $attribute->getId(),
                    'entity_id'    => $customer->getId(),
                    'value'        => 1
                ];
                $idUpdate[]      = $customer->getId();
            }

            $result['status'] = true;
            $result['total']  = count($ids);
            $response         = $this->helperEmailMarketing->syncCustomers($data);
            $result['log']    = $response;

            if (isset($response['success'])) {
                $this->helperEmailMarketing->updateData(
                    $customers->getConnection(),
                    $idUpdate,
                    $customers->getMainTable()
                );
            }

        } catch (Exception $e) {
            $result['status']  = false;
            $result['message'] = $e->getMessage();
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }
}
