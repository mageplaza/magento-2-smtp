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

namespace Mageplaza\Smtp\Controller\AbandonedCart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Helper\Data;

/**
 * Class Recover
 * @package Mageplaza\Smtp\Controller\AbandonedCart
 */
class Recover extends Action
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var Collection
     */
    protected $quoteCollection;

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var string
     */
    protected $noticeMessage = '';

    /**
     * Recover constructor.
     *
     * @param Context $context
     * @param Data $helperData
     * @param Collection $quoteCollection
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        Data $helperData,
        Collection $quoteCollection,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->helperData      = $helperData;
        $this->quoteCollection = $quoteCollection;
        $this->storeManager    = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $token        = $this->getRequest()->getParam('token');
        $isEmCheckout = $this->getRequest()->getParam('isEmCheckout');
        $emToken      = $this->getRequest()->getParam('emToken');

        if (!$token) {
            return $this->_redirect('checkout/cart');
        }

        try {
            if ($this->recover($token)) {
                $this->messageManager->addSuccessMessage(__('The recovery succeeded.'));
            } else {
                $this->messageManager->addNoticeMessage($this->noticeMessage);
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
        }

        if ($isEmCheckout && $emToken) {
            return $this->_redirect('checkout/cart?isEmCheckout=' . $isEmCheckout . '&token=' . urlencode($emToken));
        }

        return $this->_redirect('checkout/cart');
    }

    /**
     * @param string $token
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function recover($token)
    {
        if (!$this->helperData->isEnableEmailMarketing($this->storeManager->getStore()->getId())) {
            throw new LocalizedException(__('Marketing Automation is disabled.'));
        }

        $token              = explode('_', $token);
        $quoteId            = isset($token[1]) ? base64_decode($token[1]) : '';
        $abandonedCartToken = isset($token[0]) ? $token[0] : '';

        /**
         * @var Quote $quote
         */
        $quote = $this->quoteCollection
            ->addFieldToFilter('entity_id', $quoteId)
            ->addFieldToFilter('mp_smtp_ace_token', $abandonedCartToken)
            ->getFirstItem();
        if (!$quote->getId()) {
            throw new LocalizedException(__('The link is not available for your use'));
        }

        if (!$quote->getIsActive()) {
            throw new LocalizedException(__('An error occurred while recovering your cart.'));
        }

        $customerId = (int) $quote->getCustomerId();

        if (!$customerId) {
            $this->checkoutSession->setQuoteId($quoteId);

            return true;
        }

        if (!$this->customerSession->isLoggedIn()) {
            if (!$this->customerSession->loginById($customerId)) {
                throw new LocalizedException(
                    __('An error occurred while logging in your account. Please try to log in again.')
                );
            }

            $this->customerSession->regenerateId();
        } elseif ((int) $this->customerSession->getId() !== $customerId) {
            $this->noticeMessage = __('Please login with %1', $quote->getCustomerEmail());

            return false;
        }

        return true;
    }
}
