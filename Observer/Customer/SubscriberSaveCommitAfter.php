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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\Smtp\Helper\AbandonedCart;
use Magento\Customer\Model\Customer;
use Psr\Log\LoggerInterface;

/**
 * Class SubscriberSaveCommitAfter
 * @package Mageplaza\Smtp\Observer\Customer
 */
class SubscriberSaveCommitAfter implements ObserverInterface
{
    /**
     * @var AbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerSaveCommitAfter constructor.
     * @param AbandonedCart $helperAbandonedCart
     * @param LoggerInterface $logger
     */
    public function __construct(
        AbandonedCart $helperAbandonedCart,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->helperAbandonedCart = $helperAbandonedCart;
        $this->logger              = $logger;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $subscriber = $observer->getEvent()->getDataObject();
        if ($this->helperAbandonedCart->isEnableAbandonedCart() &&
            $this->helperAbandonedCart->getSecretKey() &&
            $this->helperAbandonedCart->getAppID() &&
            $subscriber->getIsNewRecord()
        ) {
            try {

                $data = [
                    'email'        => $subscriber->getSubscriberEmail(),
                    'firstName'    => '',
                    'lastName'     => '',
                    'phoneNumber'  => '',
                    'description'  => '',
                    'source' => 'Magento',
                    'isSubscriber' => $subscriber->getSubscriberStatus()
                ];

                /**
                 * @var Customer $customer
                 */
                $customer = $this->getCustomerByEmail($subscriber->getSubscriberEmail());
                if ($customer && $customer->getId()) {
                    $data['firstName'] = $customer->getFirstname();
                    $data['lastName']  = $customer->getLastname();
                }

                $this->helperAbandonedCart->syncCustomer($data);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param string $email
     * @return \Magento\Customer\Api\Data\CustomerInterface|string
     */
    public function getCustomerByEmail($email)
    {
        try {
            return $this->customerRepository->get($email);
        } catch (Exception $e) {
            return '';
        }
    }
}
