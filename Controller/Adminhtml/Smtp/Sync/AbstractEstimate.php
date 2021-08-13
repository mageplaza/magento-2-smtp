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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Phrase;

/**
 * Class AbstractEstimate
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\Sync
 */
abstract class AbstractEstimate extends Action
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
     * AbstractEstimate constructor.
     *
     * @param Context $context
     * @param EmailMarketing $emailMarketing
     */
    public function __construct(
        Context $context,
        EmailMarketing $emailMarketing
    ) {
        $this->emailMarketing = $emailMarketing;

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

            $daysRange  = $this->getRequest()->getParam('days_range');
            $from       = $this->getRequest()->getParam('from');
            $to         = $this->getRequest()->getParam('to');
            $collection = $this->prepareCollection();

            if ($this->emailMarketing->isOnlyNotSync()) {
                $collection->addFieldToFilter('mp_smtp_email_marketing_synced', 0);
            }

            $storeId    = $this->getRequest()->getParam('storeId');
            $websiteId  = $this->getRequest()->getParam('websiteId');

            if ($storeId) {
                $collection->addFieldToFilter($this->storeIdField, $storeId);
            }

            if ($websiteId) {
                $collection->addFieldToFilter($this->websiteIdField, $websiteId);
            }

            if (!$collection instanceof SubscriberCollection && $query = $this->emailMarketing->queryExpr(
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
                $result['message'] = $this->getZeroMessage();
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
     * @return AbstractCollection
     */
    abstract public function prepareCollection();

    /**
     * @return Phrase
     */
    abstract public function getZeroMessage();
}
