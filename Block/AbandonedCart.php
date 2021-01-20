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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\Store;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Config;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Block
 */
class AbandonedCart extends Template
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var PriceCurrency
     */
    protected $priceCurrency;

    /**
     * @var Data
     */
    protected $taxHelper;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * AbandonedCart constructor.
     *
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrency $priceCurrency
     * @param QuoteFactory $quoteFactory
     * @param EmailMarketing $helperEmailMarketing
     * @param array $data
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        PriceCurrency $priceCurrency,
        QuoteFactory $quoteFactory,
        EmailMarketing $helperEmailMarketing,
        array $data = []
    ) {
        $this->_productRepository = $productRepository;
        $this->priceCurrency = $priceCurrency;
        $this->taxHelper = $context->getTaxData();
        $this->quoteFactory = $quoteFactory;
        $this->helperEmailMarketing = $helperEmailMarketing;
        parent::__construct($context, $data);
    }

    /**
     * @return Quote|null
     */
    public function getQuote()
    {
        if ($quoteId = $this->getData('quote_id')) {
            return $this->quoteFactory->create()->load($quoteId);
        }

        return null;
    }

    /**
     * @return EmailMarketing
     */
    public function getHelperEmailMarketing()
    {
        return $this->helperEmailMarketing;
    }

    /**
     * Get items in quote
     *
     * @return Item[]
     */
    public function getProductCollection()
    {
        $items = [];

        if ($quote = $this->getQuote()) {
            return $quote->getAllVisibleItems();
        }

        return $items;
    }

    /**
     * Get subtotal in quote
     *
     * @param bool $inclTax
     *
     * @return float|string
     */
    public function getSubtotal($inclTax = false)
    {
        $subtotal = 0;
        if ($quote = $this->getQuote()) {
            $address  = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
            $subtotal = $inclTax ? $address->getSubtotalInclTax() : $address->getSubtotal();
        }

        return $this->formatPrice($subtotal, $quote ? $quote->getStoreId() : null);
    }

    /**
     * @param int|float $value
     * @param int $storeId
     *
     * @return float|string
     */
    public function formatPrice($value, $storeId)
    {
        return $this->priceCurrency->format(
            $value,
            true,
            PriceCurrency::DEFAULT_PRECISION,
            $storeId
        );
    }

    /**
     * @param Item $item
     *
     * @return string|string[]|null
     */
    public function getProductImage(Item $item)
    {
        $productId = $item->getProductId();
        try {
            /** @var Product $product */
            $product = $this->_productRepository->getById($productId);
            /** @var Store $store */
            $store    = $this->_storeManager->getStore();
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();

            return str_replace('\\', '/', $imageUrl);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @return Config
     */
    public function getTaxConfig()
    {
        return $this->taxHelper->getConfig();
    }
}
