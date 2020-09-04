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

use Exception;
use Magento\Bundle\Helper\Catalog\Product\Configuration as BundleConfiguration;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Product\Configuration as CatalogConfiguration;
use Magento\Catalog\Model\ProductRepository;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\ResourceModel\Quote as ResourceQuote;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Helper
 */
class AbandonedCart extends Data
{
    //const APP_URL             = 'https://app.avada.io/webhook/abandonedCart';
    const APP_URL             = 'https://get-market-staging.firebaseapp.com/webhook/checkout/create';
    const CUSTOMER_URL        = 'https://app.avada.io/webhook/customer/create';

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
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var ResourceQuote
     */
    protected $resourceQuote;

    /**
     * @var string
     */
    protected $url = '';

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
     * @param EncryptorInterface $encryptor
     * @param Curl $curl
     * @param ProductRepository $productRepository
     * @param ResourceQuote $resourceQuote
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
        CatalogHelper $catalogHelper,
        EncryptorInterface $encryptor,
        Curl $curl,
        ProductRepository $productRepository,
        ResourceQuote $resourceQuote
    ) {
        parent::__construct($context, $objectManager, $storeManager);

        $this->frontendUrl                = $frontendUrl;
        $this->escaper                    = $escaper;
        $this->productConfig              = $catalogConfiguration;
        $this->bundleProductConfiguration = $bundleProductConfiguration;
        $this->priceCurrency              = $priceCurrency;
        $this->catalogHelper              = $catalogHelper;
        $this->encryptor                  = $encryptor;
        $this->_curl                      = $curl;
        $this->resourceQuote              = $resourceQuote;
        $this->productRepository          = $productRepository;
    }

    /**
     * @return ResourceQuote
     */
    public function getResourceQuote()
    {
        return $this->resourceQuote;
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
     * @param Quote $quote
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getRecoveryUrl(Quote $quote)
    {
        $store       = $this->storeManager->getStore($quote->getStoreId());
        $routeParams = [
            '_current' => false,
            '_nosid'   => true,
            'token'    => $quote->getMpSmtpAceToken() . '_' . base64_encode($quote->getId()),
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

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getAppID($storeId = null)
    {
        return $this->getAbandonedCartConfig('app_id', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getSecretKey($storeId = null)
    {
        $secretKey = $this->getAbandonedCartConfig('secret_key', $storeId);

        return $this->encryptor->decrypt($secretKey);
    }

    /**
     * @param int $quoteId
     *
     * @return string
     * @throws LocalizedException
     */
    public function getQuoteUpdatedAt($quoteId)
    {
        $connection = $this->getResourceQuote()->getConnection();
        $select     = $connection->select()->from($this->getResourceQuote()
            ->getMainTable(), 'updated_at')
            ->where('entity_id = :id');

        return $connection->fetchOne($select, [':id' => $quoteId]);
    }

    /**
     * @param Quote $quote
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getACEData($quote)
    {
        $isActive         = (bool) $quote->getIsActive();
        $quoteCompletedAt = null;
        $updatedAt = $this->getQuoteUpdatedAt($quote->getId());

        //first time created is the same updated
        $createdAt = $quote->getCreatedAt() ?: $updatedAt;
        if (!$isActive) {
            $quoteCompletedAt = $updatedAt;
        }

        return [
            'id'                     => (int) $quote->getId(),
            'email'                  => $quote->getCustomerEmail(),
            'completed_at'           => $quoteCompletedAt,
            'customer'               => [
                'id'         => (int) $quote->getCustomerId(),
                'email'      => $quote->getCustomerEmail(),
                'name'       => $this->getCustomerName($quote),
                'first_name' => $quote->getCustomerFirstname(),
                'last_name'  => $quote->getCustomerLastname()
            ],
            'line_items'             => $this->getCartItems($quote),
            'currency'               => $quote->getStoreCurrencyCode(),
            'presentment_currency'   => $quote->getStoreCurrencyCode(),
            'created_at'             => $createdAt,
            'updated_at'             => $updatedAt,
            'abandoned_checkout_url' => $this->getRecoveryUrl($quote),
            'subtotal_price'         => $quote->getSubtotal(),
            'total_price'            => $quote->getGrandTotal(),
            'total_tax'              => !$quote->isVirtual() ? $quote->getShippingAddress()->getTaxAmount() : 0,
            'customer_locale'        => null
        ];
    }

    /**
     * @param Quote $quote
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCartItems(Quote $quote)
    {
        $items = [];
        foreach ($quote->getItemsCollection() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $productType = $item->getData('product_type');
            $bundleItems = [];
            $hasVariant  = $productType === 'configurable';
            $isBundle    = $productType === 'bundle';

            $itemRequest = [
                'title'         => $item->getName(),
                'price'         => (float) $item->getPrice(),
                'quantity'      => (int) $item->getQty(),
                'sku'           => $item->getSku(),
                'product_id'    => $item->getProductId(),
                'image'         => $this->getProductImage($item->getProduct()),
                'frontend_link' => $item->getProduct()->getProductUrl(),
                'line_price'    => $item->getRowTotal()
            ];

            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    if ($hasVariant) {
                        $itemRequest['variant_title'] = $child->getName();
                        $itemRequest['variant_image'] = $this->getProductImage($child->getProduct());
                        $itemRequest['variant_id']    = $child->getProductId();
                        $itemRequest['variant_price'] = (float)$child->getPrice();
                    }

                    if ($isBundle) {
                        $bundleItems[] = [
                            'title'      => $child->getName(),
                            'image'      => $this->getProductImage($child->getProduct()),
                            'product_id' => $child->getProductId(),
                            'sku'        => $child->getSku(),
                            'quantity'   => (int)$child->getQty(),
                            'price'      => (float)$child->getPrice(),
                        ];
                    }
                }
            }

            $itemRequest['bundle_items'] = $bundleItems;
            $items[] = $itemRequest;
        }

        return $items;
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
            false,
            PriceCurrency::DEFAULT_PRECISION,
            $storeId
        );
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProductImage($product)
    {
        $baseUrl  = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $baseUrl . 'catalog/product' . $product->getSmallImage();

        return str_replace('\\', '/', $imageUrl);
    }

    /**
     * @param array $data
     * @param string $appID
     * @param string $url
     * @param string $secretKey
     * @param bool $isTest
     * @return mixed
     * @throws LocalizedException
     */
    public function sendRequest($data, $url = '', $appID = '', $secretKey = '', $isTest = false)
    {
        $body = $this->setHeaders($data, $url, $appID, $secretKey, $isTest);
        $this->_curl->post($this->url, $body);
        $body     = $this->_curl->getBody();
        $bodyData = self::jsonDecode($body);
        if (!isset($bodyData['success']) || !$bodyData['success']) {
            throw new LocalizedException(__('Error : %1', isset($bodyData['message']) ? $bodyData['message'] : ''));
        }

        return $bodyData;
    }

    /**
     * @param array $data
     * @param string $url
     * @param string $appID
     * @param string $secretKey
     * @param bool $isTest
     *
     * @return string
     */
    public function setHeaders($data, $url = '', $appID = '', $secretKey = '', $isTest = false)
    {
        if (!$url) {
            $url = self::APP_URL;
        }

        $this->url = $url;

        $body          = self::jsonEncode(['data' => $data]);
        $secretKey     = $secretKey ?: $this->getSecretKey();
        $generatedHash = base64_encode(hash_hmac('sha256', $body, $secretKey, true));
        $appID         = $appID ?: $this->getAppID();

        $this->_curl->setHeaders([
            'Content-Type'                     => 'application/json',
            'X-EmailMarketing-Hmac-Sha256'     => $generatedHash,
            'X-EmailMarketing-App-Id'          => $appID,
            'X-EmailMarketing-Connection-Test' => $isTest
        ]);

        return $body;
    }

    /**
     * @param array $data
     * @param string $url
     * @param string $appID
     * @param string $secretKey
     */
    public function sendRequestWithoutWaitResponse($data, $url = '', $appID = '', $secretKey = '')
    {
        try {
            $body = $this->setHeaders($data, $url, $appID, $secretKey);
            $this->_curl->setOption(CURLOPT_TIMEOUT_MS, 500);
            $this->_curl->post($this->url, $body);
        } catch (Exception $e) {
            // Ignore exception timeout
        }
    }

    /**
     * @param string $appID
     * @param string $secretKey
     * @return mixed
     * @throws LocalizedException
     */
    public function testConnection($appID, $secretKey)
    {
        if ($secretKey === '******') {
            $secretKey = $this->getSecretKey();
        }

        return $this->sendRequest([['test' => 1]], '', $appID, $secretKey, true);
    }

    /**
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     */
    public function syncCustomer($data)
    {
        return $this->sendRequest($data, self::CUSTOMER_URL);
    }
}
