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
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class Order
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class Order extends Action
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
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * Order constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param CustomerFactory $customerFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        CustomerFactory $customerFactory,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->helperEmailMarketing   = $helperEmailMarketing;
        $this->customerFactory        = $customerFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $result = [];
        try {
            $orderCollection = $this->orderCollectionFactory->create();
            $ids = $this->getRequest()->getParam('ids');

            $orders = $orderCollection->addFieldToFilter('entity_id', ['in' => $ids]);

            $data = [];
            $idUpdate = [];
            foreach ($orders as $order) {
                $data[] = $this->helperEmailMarketing->getOrderData($order);
                $idUpdate[]  = $order->getId();
            }

            $result['status'] = true;
            $result['total'] = count($ids);
            $response = $this->helperEmailMarketing->syncOrders($data);
            if (isset($response['success'])) {
                $this->updateData(
                    $orders->getConnection(),
                    $idUpdate,
                    $orders->getMainTable()
                );
            }

        } catch (Exception $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }

    /**
     * @param AdapterInterface $connection
     * @param array $ids
     * @param string $table
     *
     * @throws Exception
     */
    public function updateData($connection, $ids, $table)
    {
        $connection->beginTransaction();
        try {
            $where      = ['entity_id IN (?)' => $ids];
            $connection->update(
                $table,
                ['mp_smtp_email_marketing_synced' => 1],
                $where
            );
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
