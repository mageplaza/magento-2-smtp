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

namespace Mageplaza\Smtp\Helper;

use Magento\Bundle\Helper\Catalog\Product\Configuration as BundleConfiguration;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Product\Configuration as CatalogConfiguration;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Helper
 */
class AbandonedCart extends Data
{
    /**
     * @var UrlInterface
     */
    protected $frontendUrl;

    /**
     * Escaper
     *
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var CatalogConfiguration
     */
    protected $productConfig;

    /**
     * Bundle catalog product configuration
     *
     * @var BundleConfiguration
     */
    protected $bundleProductConfiguration;

    /***
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var CatalogHelper
     */
    protected $catalogHelper;

    /**
     * AbandonedCart constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $frontendUrl
     * @param Escaper $escaper
     * @param CatalogConfiguration $catalogConfiguration
     * @param BundleConfiguration $bundleProductConfiguration
     * @param PriceCurrencyInterface $priceCurrency
     * @param CatalogHelper $catalogHelper
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        UrlInterface $frontendUrl,
        Escaper $escaper,
        CatalogConfiguration $catalogConfiguration,
        BundleConfiguration $bundleProductConfiguration,
        PriceCurrencyInterface $priceCurrency,
        CatalogHelper $catalogHelper
    ) {
        parent::__construct($context, $objectManager, $storeManager);

        $this->frontendUrl                = $frontendUrl;
        $this->escaper                    = $escaper;
        $this->productConfig              = $catalogConfiguration;
        $this->bundleProductConfiguration = $bundleProductConfiguration;
        $this->priceCurrency              = $priceCurrency;
        $this->catalogHelper              = $catalogHelper;
    }

    /**
     * @param Item $item
     *
     * @return array
     */
    public function getProductOptions(Item $item)
    {
        if ($item->getProductType() === 'bundle') {
            return $this->bundleProductConfiguration->getOptions($item);
        }

        return $this->productConfig->getOptions($item);
    }

    /**
     * @param string $sku
     *
     * @return string[]
     */
    public function splitSku($sku)
    {
        return $this->catalogHelper->splitSku($sku);
    }

    /**
     * @param array $optionValue
     *
     * @return array
     */
    public function getFormatedOptionValue(array $optionValue)
    {
        $params = [
            'max_length' => 55,
            'cut_replacer' => ' <a href="#" class="dots tooltip toggle" onclick="return false">...</a>'
        ];

        return $this->productConfig->getFormattedOptionValue($optionValue, $params);
    }

    /**
     * @param string $token
     * @param Quote $quote
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getRecoveryUrl($token, Quote $quote)
    {
        $store       = $this->storeManager->getStore($quote->getStoreId());
        $routeParams = [
            '_current' => false,
            '_nosid'   => true,
            'token'    => $token . '_' . base64_encode($quote->getId()),
            '_secure'  => $store->isUrlSecure()
        ];
        $this->frontendUrl->setScope($quote->getStoreId());

        return $this->frontendUrl->getUrl('mpsmtp/abandonedcart/recover', $routeParams);
    }

    /**
     * @param Quote $quote
     *
     * @return array|string
     */
    public function getCustomerName(Quote $quote)
    {
        $customerName = trim($quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname());

        if (!$customerName) {
            $customer = $quote->getCustomerId() ? $quote->getCustomer() : null;
            if ($customer && $customer->getId()) {
                $customerName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            } else {
                $customerName = explode('@', $quote->getCustomerEmail())[0];
            }
        }

        return $this->escaper->escapeHtml($customerName);
    }
}
