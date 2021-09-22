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
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber as ModelSubscriber;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\Config\Source\Newsletter;
use Mageplaza\Smtp\Model\Config\Source\SyncOptions;
use Mageplaza\Smtp\Model\Config\Source\SyncType;
use Zend_Db_Expr;

/**
 * Class Sync
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class Sync extends Action
{
    const SUB    = 'subscribed';
    const UNSUB  = 'unsub';
    const NOTSUB = 'notsub';

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
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var SubscriberCollectionFactory
     */
    protected $subscriberCollectionFactory;

    /**
     * @var TimezoneInterface
     */
    protected $localeDate;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Sync constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param TimezoneInterface $localeDate
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        CustomerCollectionFactory $customerCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        TimezoneInterface $localeDate,
        Data $helperData
    ) {
        $this->helperEmailMarketing        = $helperEmailMarketing;
        $this->customerCollectionFactory   = $customerCollectionFactory;
        $this->orderCollectionFactory      = $orderCollectionFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->localeDate                  = $localeDate;
        $this->helperData                  = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $daysRange   = $this->getRequest()->getParam('daysRange');
        $from        = $this->getRequest()->getParam('from');
        $to          = $this->getRequest()->getParam('to');
        $type        = $this->getRequest()->getParam('type');
        $syncOptions = $this->getRequest()->getParam('syncOptions');
        $result      = [];

        switch ($type) {
            case SyncType::CUSTOMERS:
                $result = $this->syncCustomers($syncOptions, $daysRange, $from, $to);
                break;
            case SyncType::ORDERS:
                $result = $this->syncOrders($syncOptions, $daysRange, $from, $to);
                break;
            case SyncType::SUBSCRIBERS:
                $result = $this->syncSubscribers($syncOptions);
                break;
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }

    /**
     * @param string $syncOptions
     * @param string $daysRange
     * @param string $from
     * @param string $to
     *
     * @return mixed
     */
    public function syncCustomers($syncOptions, $daysRange, $from, $to)
    {
        try {
            $attribute          = $this->helperEmailMarketing->getSyncedAttribute();
            $customerCollection = $this->customerCollectionFactory->create();
            $ids                = $this->getRequest()->getParam('ids');
            $subscriberTable    = $customerCollection->getTable('newsletter_subscriber');
            $customerCollection->getSelect()->columns(
                [
                    'subscriber_status' => new Zend_Db_Expr(
                        '(SELECT `s`.`subscriber_status` FROM `'
                        . $subscriberTable . '` as `s` WHERE `s`.`customer_id` = `e`.`entity_id` LIMIT 1)'
                    )
                ]
            );

            $customers = $customerCollection->addFieldToFilter('entity_id', ['in' => $ids]);

            if ($syncOptions === SyncOptions::NOT_SYNC) {
                if ($this->helperData->versionCompare('2.4.0')) {
                    $customers->getSelect()->where('mp_smtp_email_marketing_synced = ?', 0);
                } else {
                    $customers->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
                }
            }

            if ($daysRange !== 'lifetime'
                && $query = $this->helperEmailMarketing->queryExpr($daysRange, $from, $to, 'e')) {
                $customers->getSelect()->where($query);
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

        return $result;
    }

    /**
     * @param string $syncOptions
     * @param string $daysRange
     * @param string $from
     * @param string $to
     *
     * @return mixed
     */
    public function syncOrders($syncOptions, $daysRange, $from, $to)
    {
        try {
            $orderCollection = $this->orderCollectionFactory->create();
            $ids             = $this->getRequest()->getParam('ids');
            $orders          = $orderCollection->addFieldToFilter('entity_id', ['in' => $ids]);

            if ($syncOptions === SyncOptions::NOT_SYNC) {
                if ($this->helperData->versionCompare('2.4.0')) {
                    $orders->getSelect()->where('mp_smtp_email_marketing_synced = ?', 0);
                } else {
                    $orders->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
                }
            }

            if ($daysRange !== 'lifetime' && $query = $this->helperEmailMarketing->queryExpr($daysRange, $from, $to)) {
                $orders->getSelect()->where($query);
            }

            $data     = [];
            $idUpdate = [];

            foreach ($orders as $order) {
                $data[]     = $this->helperEmailMarketing->getOrderData($order);
                $idUpdate[] = $order->getId();
            }

            $result['status'] = true;
            $result['total']  = count($ids);
            $response         = $this->helperEmailMarketing->syncOrders($data);
            $result['log']    = $response;

            if (isset($response['success'])) {
                $this->helperEmailMarketing->updateData(
                    $orders->getConnection(),
                    $idUpdate,
                    $orders->getMainTable()
                );
            }

        } catch (Exception $e) {
            $result['status']  = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @param string $syncOptions
     *
     * @return mixed
     */
    public function syncSubscribers($syncOptions)
    {
        try {
            $collection = $this->subscriberCollectionFactory->create();
            $ids        = $this->getRequest()->getParam('ids');

            if ($this->helperEmailMarketing->getSubscriberConfig() === Newsletter::SUBSCRIBED) {
                $collection->addFieldToFilter('subscriber_status', ['eq' => ModelSubscriber::STATUS_SUBSCRIBED]);
            }

            $data        = [];
            $subscribers = $collection->addFieldToFilter('subscriber_id', ['in' => $ids]);

            if ($syncOptions === SyncOptions::NOT_SYNC) {
                if ($this->helperData->versionCompare('2.4.0')) {
                    $subscribers->getSelect()->where('mp_smtp_email_marketing_synced = ?', 0);
                } else {
                    $subscribers->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
                }
            }

            $idUpdate = [];

            foreach ($subscribers as $subscriber) {
                switch ($subscriber->getSubscriberStatus()) {
                    case ModelSubscriber::STATUS_SUBSCRIBED:
                        $status = self::SUB;
                        break;
                    case ModelSubscriber::STATUS_UNSUBSCRIBED:
                        $status = self::UNSUB;
                        break;
                    default:
                        $status = self::NOTSUB;
                        break;
                }

                $updatedAt = $this->helperEmailMarketing->formatDate($subscriber->getChangeStatusAt());

                if ($subscriber->getCustomerId()) {
                    $customerCollection = $this->customerCollectionFactory->create();
                    $customerCollection->addFieldToFilter('entity_id', ['eq' => $subscriber->getCustomerId()]);

                    foreach ($customerCollection as $customer) {
                        $customerData                 = $this->helperEmailMarketing->getCustomerData(
                            $customer,
                            false,
                            true
                        );
                        $customerData['status']       = $status;
                        $customerData['tags']         = 'newsletter';
                        $customerData['isSubscriber'] = true;
                        $customerData['isSubscriber'] = true;
                        $customerData['is_utc']       = true;
                        $customerData['updated_at']   = $updatedAt;
                        $data[]                       = $customerData;
                    }

                } else {
                    $data[] = [
                        'id'           => (int) $subscriber->getId(),
                        'email'        => $subscriber->getSubscriberEmail(),
                        'status'       => $status,
                        'source'       => 'Magento',
                        'tags'         => 'newsletter',
                        'isSubscriber' => true,
                        'timezone'     => $this->localeDate->getConfigTimezone(
                            ScopeInterface::SCOPE_STORE,
                            $subscriber->getStoreId()
                        ),
                        'is_utc'       => true,
                        'updated_at'   => $updatedAt
                    ];

                    $idUpdate[] = $subscriber->getId();
                }
            }

            $result['status'] = true;
            $result['total']  = count($ids);
            $response         = $this->helperEmailMarketing->syncCustomers($data);
            $result['log']    = $response;

            if (isset($response['success'])) {
                $this->helperEmailMarketing->updateData(
                    $subscribers->getConnection(),
                    $idUpdate,
                    $subscribers->getMainTable(),
                    true
                );
            }

        } catch (Exception $e) {
            $result['status']  = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
