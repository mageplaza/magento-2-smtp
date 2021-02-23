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

use Magento\Backend\App\Action\Context;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Phrase;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Mageplaza\Smtp\Model\Config\Source\Newsletter;
use Magento\Newsletter\Model\Subscriber;

/**
 * Class EstimateSubscriber
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
class EstimateSubscriber extends AbstractEstimate
{
    /**
     * @var SubscriberCollectionFactory
     */
    protected $subscriberCollectionFactory;

    /**
     * EstimateSubscriber constructor.
     *
     * @param Context $context
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param EmailMarketing $emailMarketing
     */
    public function __construct(
        Context $context,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        EmailMarketing $emailMarketing
    ) {
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;

        parent::__construct($context, $emailMarketing);
    }

    /**
     * @return AbstractCollection|Collection
     */
    public function prepareCollection()
    {
        $collection = $this->subscriberCollectionFactory->create();

        if ($this->emailMarketing->getSubscriberConfig() === Newsletter::SUBSCRIBED) {
            $collection->addFieldToFilter('subscriber_status', ['eq' => Subscriber::STATUS_SUBSCRIBED]);
        }

        return $collection;
    }

    /**
     * @return Phrase
     */
    public function getZeroMessage()
    {
        return __('No subscriber to synchronize.');
    }
}
