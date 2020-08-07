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
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\Smtp\Helper\AbandonedCart;
use Magento\Customer\Model\Customer;
use Psr\Log\LoggerInterface;

/**
 * Class CustomerSaveCommitAfter
 * @package Mageplaza\Smtp\Observer\Customer
 */
class CustomerSaveCommitAfter implements ObserverInterface
{
    /**
     * @var AbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerSaveCommitAfter constructor.
     * @param AbandonedCart $helperAbandonedCart
     * @param LoggerInterface $logger
     */
    public function __construct(AbandonedCart $helperAbandonedCart, LoggerInterface $logger)
    {
        $this->helperAbandonedCart = $helperAbandonedCart;
        $this->logger              = $logger;
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
        if ($this->helperAbandonedCart->isEnableAbandonedCart() &&
            $this->helperAbandonedCart->getSecretKey() &&
            $this->helperAbandonedCart->getAppID() &&
            $customer->getIsNewRecord()
        ) {
            try {
                $data = [
                    'email'        => $customer->getEmail(),
                    'firstName'    => $customer->getFirstname(),
                    'lastName'     => $customer->getLastname(),
                    'phoneNumber'  => '',
                    'description'  => '',
                    'isSubscriber' => $customer->getIsSubscribed(),
                    'source'       => 'Magento',
                ];

                $this->helperAbandonedCart->syncCustomer($data);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
