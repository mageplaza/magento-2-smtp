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

namespace Mageplaza\Smtp\Plugin;

use Exception;
use Magento\Customer\Model\AccountManagement as CustomerAccountManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class AccountManagement
 * @package Mageplaza\Smtp\Plugin
 */
class AccountManagement
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * AccountManagement constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param EmailMarketing $helperEmailMarketing
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        EmailMarketing $helperEmailMarketing
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->helperEmailMarketing = $helperEmailMarketing;
    }

    /**
     * @param CustomerAccountManagement $subject
     * @param $result
     * @param $customerEmail
     *
     * @return false|mixed
     * @SuppressWarnings("Unused")
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterIsEmailAvailable(CustomerAccountManagement $subject, $result, $customerEmail)
    {
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            $cartId = $this->checkoutSession->getQuote()->getId();
            if (!$cartId) {
                return $result;
            }

            /** @var Quote $quote */
            $quote = $this->cartRepository->get($cartId);
            $quote->setCustomerEmail($customerEmail);

            try {
                $this->cartRepository->save($quote);
            } catch (Exception $e) {
                return $result;
            }
        }

        return $result;
    }
}
