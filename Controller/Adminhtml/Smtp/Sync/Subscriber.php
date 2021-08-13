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
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\Config\Source\Newsletter;
use Magento\Newsletter\Model\Subscriber as ModelSubscriber;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Class Subscriber
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class Subscriber extends Action
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
     * @var SubscriberCollectionFactory
     */
    protected $subscriberCollectionFactory;

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * @var TimezoneInterface
     */
    protected $_localeDate;

    /**
     * Subscriber constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param TimezoneInterface $localeDate
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        CustomerCollectionFactory $customerCollectionFactory,
        TimezoneInterface $localeDate
    ) {
        $this->helperEmailMarketing        = $helperEmailMarketing;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->customerCollectionFactory   = $customerCollectionFactory;
        $this->_localeDate                 = $localeDate;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $result = [];

        try {
            $collection = $this->subscriberCollectionFactory->create();
            $ids        = $this->getRequest()->getParam('ids');

            if ($this->helperEmailMarketing->getSubscriberConfig() === Newsletter::SUBSCRIBED) {
                $collection->addFieldToFilter('subscriber_status', ['eq' => ModelSubscriber::STATUS_SUBSCRIBED]);
            }

            $data        = [];
            $subscribers = $collection->addFieldToFilter('subscriber_id', ['in' => $ids]);

            if ($this->helperEmailMarketing->isOnlyNotSync()) {
                $subscribers->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
            }

            $idUpdate    = [];

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
                        'timezone'     => $this->_localeDate->getConfigTimezone(
                            ScopeInterface::SCOPE_STORE,
                            $subscriber->getStoreId()
                        )
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

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }
}
