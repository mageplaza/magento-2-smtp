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

namespace Mageplaza\Smtp\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Mageplaza\Smtp\Api\CheckoutManagementInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class GuestCheckoutManagement
 * @package Mageplaza\Smtp\Model
 */
class CheckoutManagement implements CheckoutManagementInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var QuoteIdMaskResource
     */
    protected $quoteIdMaskResource;

    /**
     * CheckoutManagement constructor.
     *
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param EmailMarketing $helperEmailMarketing
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        EmailMarketing $helperEmailMarketing,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->quoteIdMaskFactory   = $quoteIdMaskFactory;
        $this->cartRepository       = $cartRepository;
        $this->helperEmailMarketing = $helperEmailMarketing;
    }

    /**
     * @param string $cartId
     * @param string $address
     * @param bool $isOsc
     * @return null
     */
    public function updateOrder($cartId, $address, $isOsc)
    {
        if ($this->helperEmailMarketing->isEnableEmailMarketing() &&
            $this->helperEmailMarketing->getSecretKey() &&
            $this->helperEmailMarketing->getAppID()
        ) {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResource->load($quoteIdMask, $cartId, 'masked_id');
            /** @var Quote $quote */
            $quote           = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
            $newAddress = EmailMarketing::jsonDecode($address);
            $ACEData         = $this->helperEmailMarketing->getACEData($quote, $newAddress, $isOsc);

            $this->helperEmailMarketing->sendRequestWithoutWaitResponse(
                $ACEData,
                EmailMarketing::CHECKOUT_URL
            );
        }
    }
}
