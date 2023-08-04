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
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\SessionFactory as CustomerSession;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Framework\Registry;

/**
 * Class Script
 * @package Mageplaza\Smtp\Block
 */
class Script extends Template implements IdentityInterface
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
     * @var Data
     */
    protected $taxHelper;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * Script constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Registry $registry
     * @param HttpContext $httpContext
     * @param Data $taxHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        Session $checkoutSession,
        CustomerSession $customerSession,
        Registry $registry,
        HttpContext $httpContext,
        Data $taxHelper,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->checkoutSession      = $checkoutSession;
        $this->customerSession      = $customerSession;
        $this->registry             = $registry;
        $this->httpContext          = $httpContext;
        $this->taxHelper            = $taxHelper;
        $this->priceCurrency        = $priceCurrency;

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
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCustomerData()
    {
        $isLoggedIn = $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
        if (!$isLoggedIn) {
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            $data            = [
                'email'     => $shippingAddress->getData('email') === null ? '' : $shippingAddress->getData('email'),
                'firstname' => $shippingAddress->getData('firstname') === null ?
                    '' : $shippingAddress->getData('firstname'),
                'lastname'  => $shippingAddress->getData('lastname') === null ?
                    '' : $shippingAddress->getData('lastname')
            ];

            return $data;
        } else {
            $customer = $this->customerSession->create()->getCustomer();
            $data     = [
                'email'     => $customer->getData('email') === null ? '' : $customer->getData('email'),
                'firstname' => $customer->getData('firstname') === null ? '' : $customer->getData('firstname'),
                'lastname'  => $customer->getData('lastname') === null ? '' : $customer->getData('lastname')
            ];

            return $data;
        }
    }

    /**
     * @return array|bool
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
                'price'       => $this->getPrice($product),
                'priceTax'    => $this->getPrice($product, true),
                'productType' => $product->getTypeId(),
                'tags'        => [],
                'title'       => $this->escapeHtml($product->getName()),
                'url'         => $product->getProductUrl(),
                'vendor'      => 'magento'
            ];
        }

        return false;
    }

    /**
     * @param Product $product
     * @param bool $includeTax
     *
     * @return float
     */
    public function getPrice($product, $includeTax = false)
    {
        $price = number_format($this->priceCurrency->convert($product->getFinalPrice()), 2);

        if ($product->getTypeId() === 'configurable') {
            $price = number_format($product->getFinalPrice(), 2);
        }

        if ($includeTax) {
            $price = number_format($this->priceCurrency->convert(
                $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), true)
            ), 2);

            if ($product->getTypeId() === 'configurable') {
                $price = number_format(
                    $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), true),
                    2
                );
            }
        }

        return $price;
    }

    /**
     * @return array|string[]
     */
    public function getIdentities()
    {
        return [EmailMarketing::CACHE_TAG];
    }
}
