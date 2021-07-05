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

namespace Mageplaza\Smtp\Block;

use Magento\Catalog\Block\Product\Context;
use Magento\Customer\Model\SessionFactory as CustomerSession;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Framework\Registry;

/**
 * Class Script
 * @package Mageplaza\Smtp\Block
 */
class Script extends Template
{
    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * Script constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Registry $registry
     * @param HttpContext $httpContext
     * @param array $data
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        Session $checkoutSession,
        CustomerSession $customerSession,
        Registry $registry,
        HttpContext $httpContext,
        array $data = []
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->checkoutSession      = $checkoutSession;
        $this->customerSession      = $customerSession;
        $this->registry             = $registry;
        $this->httpContext          = $httpContext;
        parent::__construct($context, $data);
    }

    /**
     * @return EmailMarketing
     */
    public function getHelperEmailMarketing()
    {
        return $this->helperEmailMarketing;
    }

    /**
     * @return bool
     */
    public function isSuccessPage()
    {
        $fullActionName = $this->getRequest()->getFullActionName();
        $pages          = ['checkout_onepage_success', 'mpthankyoupage_index_index'];

        return in_array($fullActionName, $pages);
    }

    /**
     * @return Order
     */
    public function getCurrentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        if (!$this->helperEmailMarketing->isEnableEmailMarketing()) {
            return '';
        }

        return parent::toHtml();
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCustomerData()
    {
        $isLoggedIn = $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);
        if (!$isLoggedIn) {
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            $data = [
                'email' => $shippingAddress->getData('email') === null ? '' : $shippingAddress->getData('email'),
                'firstname' => $shippingAddress->getData('firstname') === null ? '' : $shippingAddress->getData('firstname'),
                'lastname' => $shippingAddress->getData('lastname') === null ? '' : $shippingAddress->getData('lastname')
            ];
            return $data;
        } else {
            $customer = $this->customerSession->create()->getCustomer();
            $data = [
                'email' => $customer->getData('email') === null ? '' : $customer->getData('email'),
                'firstname' => $customer->getData('firstname') === null ? '' : $customer->getData('firstname'),
                'lastname' => $customer->getData('lastname') === null ? '' : $customer->getData('lastname')
            ];
            return $data;
        }
    }

    /**
     * @return array|false
     * @throws NoSuchEntityException
     */
    public function productAbandoned()
    {
        if ($this->getRequest()->getFullActionName() === 'catalog_product_view') {
            $product  = $this->registry->registry('current_product');
            $imageUrl = $this->_storeManager->getStore()
                    ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();

            return [
                'collections' => [],
                'id'          => $product->getId(),
                'image'       => $imageUrl,
                'price'       => $product->getFinalPrice(),
                'productType' => $product->getTypeId(),
                'tags'        => [],
                'title'       => $product->getName(),
                'url'         => $product->getProductUrl(),
                'vendor'      => 'magento'
            ];
        }

        return false;
    }
}
