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

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class ModelSaveBefore
 * @package Mageplaza\Smtp\Observer\Customer
 */
class ModelSaveBefore implements ObserverInterface
{
    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * ModelSaveBefore constructor.
     *
     * @param CustomerFactory $customerFactory
     * @param EmailMarketing $helperEmailMarketing
     */
    public function __construct(
        CustomerFactory $customerFactory,
        EmailMarketing $helperEmailMarketing
    ) {
        $this->customerFactory = $customerFactory;
        $this->helperEmailMarketing = $helperEmailMarketing;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            $dataObject = $observer->getEvent()->getDataObject();

            if (!$dataObject->getId()) {
                //isObjectNew can't use on this case
                $dataObject->setIsNewRecord(true);
            } elseif ($dataObject instanceof Customer) {
                $customOrigObject = $this->customerFactory->create()->load($dataObject->getId());
                $dataObject->setCustomOrigObject($customOrigObject);
            }
        }
    }
}
