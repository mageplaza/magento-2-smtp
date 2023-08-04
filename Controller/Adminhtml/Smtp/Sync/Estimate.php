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
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Framework\Phrase;
use Mageplaza\Smtp\Model\Config\Source\Newsletter;
use Mageplaza\Smtp\Model\Config\Source\SyncOptions;
use Mageplaza\Smtp\Model\Config\Source\SyncType;

/**
 * Class Estimate
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class Estimate extends Action
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
    protected $emailMarketing;

    /**
     * @var string
     */
    protected $websiteIdField = 'website_id';

    /**
     * @var string
     */
    protected $storeIdField = 'store_id';

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
     * @var Data
     */
    protected $helperData;

    /**
     * Estimate constructor.
     *
     * @param Context $context
     * @param EmailMarketing $emailMarketing
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        EmailMarketing $emailMarketing,
        CustomerCollectionFactory $customerCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        Data $helperData
    ) {
        $this->emailMarketing              = $emailMarketing;
        $this->customerCollectionFactory   = $customerCollectionFactory;
        $this->orderCollectionFactory      = $orderCollectionFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->helperData                  = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {

            if (!$this->emailMarketing->getAppID() || !$this->emailMarketing->getSecretKey()) {
                throw new LocalizedException(__('App ID or Secret Key is empty'));
            }

            $daysRange   = $this->getRequest()->getParam('daysRange');
            $from        = $this->getRequest()->getParam('from');
            $to          = $this->getRequest()->getParam('to');
            $type        = $this->getRequest()->getParam('type');
            $syncOptions = $this->getRequest()->getParam('syncOptions');
            $collection  = $this->prepareCollection($type);

            if ($syncOptions === SyncOptions::NOT_SYNC) {
                if ($this->helperData->versionCompare('2.4.0')) {
                    $collection->getSelect()->where('mp_smtp_email_marketing_synced = ?', 0);
                } else {
                    $collection->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
                }
            }

            $storeId   = $this->getRequest()->getParam('storeId');
            $websiteId = $this->getRequest()->getParam('websiteId');

            if ($storeId) {
                $collection->addFieldToFilter($this->storeIdField, $storeId);
            }

            if ($websiteId) {
                $collection->addFieldToFilter($this->websiteIdField, $websiteId);
            }

            if (!$collection instanceof SubscriberCollection && $daysRange !== 'lifetime'
                && $query = $this->emailMarketing->queryExpr(
                    $daysRange,
                    $from,
                    $to,
                    $collection instanceof Collection ? 'e' : 'main_table'
                )
            ) {
                $collection->getSelect()->where($query);
            }

            $ids             = $collection->getAllIds();
            $result['ids']   = $ids;
            $result['total'] = count($ids);

            if ($result['total'] === 0) {
                $result['message'] = $this->getZeroMessage($type);
            }

            $result['status'] = true;
        } catch (Exception $e) {
            $result = [
                'status'  => false,
                'message' => $e->getMessage()
            ];
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }

    /**
     * @param int $type
     *
     * @return bool|Collection|SubscriberCollection|OrderCollection
     */
    public function prepareCollection($type)
    {
        switch ($type) {
            case SyncType::CUSTOMERS:
                return $this->customerCollectionFactory->create();
            case SyncType::ORDERS:
                $orderCollection      = $this->orderCollectionFactory->create();
                $storeTable           = $orderCollection->getTable('store');
                $this->websiteIdField = 'store_table.website_id';
                $this->storeIdField   = 'main_table.store_id';
                $orderCollection->getSelect()->join(
                    ['store_table' => $storeTable],
                    'main_table.store_id = store_table.store_id',
                    [
                        $this->websiteIdField
                    ]
                );

                return $orderCollection;
            case SyncType::SUBSCRIBERS:
                $collection = $this->subscriberCollectionFactory->create();

                if ($this->emailMarketing->getSubscriberConfig() === Newsletter::SUBSCRIBED) {
                    $collection->addFieldToFilter('subscriber_status', ['eq' => Subscriber::STATUS_SUBSCRIBED]);
                }

                return $collection;
            default:
                return false;
        }
    }

    /**
     * @param int $type
     *
     * @return Phrase|string
     */
    public function getZeroMessage($type)
    {
        switch ($type) {
            case SyncType::CUSTOMERS:
                return __('No customers to synchronize.');
            case SyncType::ORDERS:
                return __('No Orders to synchronize.');
            case SyncType::SUBSCRIBERS:
                return __('No subscriber to synchronize.');
            default:
                return '';
        }
    }
}
