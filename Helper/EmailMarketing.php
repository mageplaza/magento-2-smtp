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
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Customer\Model\Attribute;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
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
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\ResourceModel\Quote as ResourceQuote;
use Magento\Reports\Model\ResourceModel\Order\CollectionFactory as ReportOrderCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class EmailMarketing
 * @package Mageplaza\Smtp\Helper
 */
class EmailMarketing extends Data
{
    const IS_SYNCED_ATTRIBUTE = 'mp_smtp_is_synced';

    const APP_URL = 'https://app.avada.io/webhook/checkout/create';
    const CUSTOMER_URL = 'https://app.avada.io/webhook/customer/create';
    const CUSTOMER_UPDATE_URL = 'https://app.avada.io/webhook/customer/update';
    const ORDER_URL = 'https://app.avada.io/webhook/order/processing';
    const DELETE_URL = 'https://app.avada.io/webhook/checkout?id=';
    const SYNC_CUSTOMER_URL = 'https://app.avada.io/sync/customer';

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
     * @var ShippingHelper
     */
    protected $shippingHelper;

    /**
     * @var Attribute
     */
    protected $customerAttribute;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $storeId = '';

    /**
     * @var bool
     */
    protected $isSyncCustomer = false;

    /**
     * @var OrderCollection
     */
    protected $orderCollection;

    /**
     * @var ReportOrderCollectionFactory
     */
    protected $reportCollectionFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Subscriber factory
     *
     * @var SubscriberFactory
     */
    protected $_subscriberFactory;

    /**
     * EmailMarketing constructor.
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
     * @param ShippingHelper $shippingHelper
     * @param Attribute $customerAttribute
     * @param OrderCollection $orderCollection
     * @param ReportOrderCollectionFactory $reportCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param SubscriberFactory $subscriberFactory
     * @param LoggerInterface $logger
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
        ResourceQuote $resourceQuote,
        ShippingHelper $shippingHelper,
        Attribute $customerAttribute,
        OrderCollection $orderCollection,
        ReportOrderCollectionFactory $reportCollectionFactory,
        CustomerFactory $customerFactory,
        SubscriberFactory $subscriberFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context, $objectManager, $storeManager);

        $this->frontendUrl = $frontendUrl;
        $this->escaper = $escaper;
        $this->productConfig = $catalogConfiguration;
        $this->bundleProductConfiguration = $bundleProductConfiguration;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;
        $this->encryptor = $encryptor;
        $this->_curl = $curl;
        $this->resourceQuote = $resourceQuote;
        $this->productRepository = $productRepository;
        $this->shippingHelper = $shippingHelper;
        $this->customerAttribute = $customerAttribute;
        $this->orderCollection = $orderCollection;
        $this->reportCollectionFactory = $reportCollectionFactory;
        $this->customerFactory = $customerFactory;
        $this->logger = $logger;
        $this->_subscriberFactory = $subscriberFactory;
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
        $store = $this->storeManager->getStore($quote->getStoreId());
        $routeParams = [
            '_current' => false,
            '_nosid' => true,
            'token' => $quote->getMpSmtpAceToken() . '_' . base64_encode($quote->getId()),
            '_secure' => $store->isUrlSecure()
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
        return $this->getEmailMarketingConfig('app_id', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getSecretKey($storeId = null)
    {
        $secretKey = $this->getEmailMarketingConfig('secret_key', $storeId);

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
        $select = $connection->select()->from($this->getResourceQuote()
            ->getMainTable(), 'updated_at')
            ->where('entity_id = :id');

        return $connection->fetchOne($select, [':id' => $quoteId]);
    }

    /**
     * @param Shipment|Creditmemo|Order $object
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getOrderData($object)
    {
        $data = [
            'id' => $object->getId(),
            'currency' => $object->getOrderCurrencyCode(),
            'created_at' => $object->getCreatedAt(),
            'updated_at' => $object->getUpdatedAt()
        ];

        $path = null;
        $customerEmail = $object->getCustomerEmail();
        $customerId = $object->getCustomerId();
        $customerFirstname = $object->getCustomerFirstname();
        $customerLastname = $object->getCustomerLastname();
        $isShipment = $object instanceof Shipment;
        $isCreditmemo = $object instanceof Creditmemo;
        if ($isCreditmemo || $isShipment) {
            $order = $object->getOrder();
            $customerEmail = $order->getCustomerEmail();
            $customerId = $order->getCustomerId();
            $customerFirstname = $order->getCustomerFirstname();
            $customerLastname = $order->getCustomerLastname();
            $data['order_id'] = $object->getOrderId();

            $path = 'sales/order/creditmemo';
            if ($isShipment) {
                $path = 'sales/order/shipment';
            }
        }

        $data['email'] = $customerEmail;
        $data['customer'] = [
            'id' => $customerId,
            'email' => $customerEmail,
            'first_name' => $customerFirstname ?: '',
            'last_name' => $customerLastname ?: '',
            'telephone' => $object->getBillingAddress()->getTelephone() ?: ''
        ];
        $data['order_status_url'] = $this->getOrderViewUrl($object->getStoreId(), $object->getId(), $path);

        if ($isShipment) {
            if ($object->getData('tracks')) {
                $data['trackingUrl'] = $this->shippingHelper->getTrackingPopupUrlBySalesModel($object);
                $tracks = [];
                foreach ($object->getData('tracks') as $track) {
                    $tracks[] = [
                        'company' => $track->getTitle(),
                        'number' => $track->getTrackNumber(),
                        'url' => $this->shippingHelper->getTrackingPopupUrlBySalesModel($track)
                    ];
                }

                if ($tracks) {
                    $data['tracks'] = $tracks;
                }
            }

            $shippingAddress = $object->getOrder()->getShippingAddress();
            if ($shippingAddress) {
                $data['destination'] = [
                    'first_name' => $shippingAddress->getFirstname(),
                    'last_name' => $shippingAddress->getLastname(),
                    'address1' => $shippingAddress->getStreetLine(1),
                    'city' => $shippingAddress->getCity(),
                    'zip' => $shippingAddress->getPostcode(),
                    'country' => $shippingAddress->getCountryId(),
                    'phone' => $shippingAddress->getTelephone()
                ];
            }
        }

        if ($isShipment || $isCreditmemo) {
            $data['line_items'] = $this->getShipmentOrCreditmemoItems($object);

        } else {
            $data['line_items'] = $this->getCartItems($object);
        }

        if ($object instanceof Order) {
            $data['total_price'] = $object->getGrandTotal();
            $data['subtotal_price'] = $object->getSubtotal();
        }

        return $data;
    }

    /**
     * @param Shipment|Creditmemo|Order $object
     * @param string $type
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function sendOrderRequest($object, $type = 'orders/create')
    {
        $data = $this->getOrderData($object);
        $this->storeId = $object->getStoreId();
        $this->_curl->addHeader('X-EmailMarketing-Topic', $type);
        $this->sendRequest($data, self::ORDER_URL);
    }

    /**
     * @param int $storeId
     * @param int $orderId
     * @param string $path
     *
     * @return string
     */
    public function getOrderViewUrl($storeId, $orderId, $path = 'sales/order/view')
    {
        $this->frontendUrl->setScope($storeId);

        return $this->frontendUrl->getUrl($path, ['order_id' => $orderId]);
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
        $isActive = (bool)$quote->getIsActive();
        $quoteCompletedAt = null;
        $updatedAt = $this->getQuoteUpdatedAt($quote->getId());

        //first time created is the same updated
        $createdAt = $quote->getCreatedAt() ?: $updatedAt;
        if (!$isActive) {
            $quoteCompletedAt = $updatedAt;
        }

        return [
            'id' => (int)$quote->getId(),
            'email' => $quote->getCustomerEmail(),
            'completed_at' => $quoteCompletedAt,
            'customer' => [
                'id' => (int)$quote->getCustomerId(),
                'email' => $quote->getCustomerEmail(),
                'name' => $this->getCustomerName($quote),
                'first_name' => $quote->getCustomerFirstname(),
                'last_name' => $quote->getCustomerLastname()
            ],
            'line_items' => $this->getCartItems($quote),
            'currency' => $quote->getStoreCurrencyCode(),
            'presentment_currency' => $quote->getStoreCurrencyCode(),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'abandoned_checkout_url' => $this->getRecoveryUrl($quote),
            'subtotal_price' => $quote->getSubtotal(),
            'total_price' => $quote->getGrandTotal(),
            'total_tax' => !$quote->isVirtual() ? $quote->getShippingAddress()->getTaxAmount() : 0,
            'customer_locale' => null,
            'shipping_address' => $this->getShippingAddress($quote)
        ];
    }

    /**
     * @param Quote $quote
     *
     * @return array
     */
    public function getShippingAddress(Quote $quote)
    {
        $address = [];

        if (!$quote->isVirtual() && $quote->getShippingAddress()) {

            /**
             * @var Address $shippingAddress
             */
            $shippingAddress = $quote->getShippingAddress();
            $address = [
                'name' => $shippingAddress->getName(),
                'last_name' => $shippingAddress->getLastname(),
                'phone' => $shippingAddress->getTelephone(),
                'company' => $shippingAddress->getCompany(),
                'country_code' => $shippingAddress->getCountryId(),
                'zip' => $shippingAddress->getPostcode(),
                'address1' => $shippingAddress->getStreetLine(1),
                'address2' => $shippingAddress->getStreetLine(2),
                'city' => $shippingAddress->getCity(),
                'province_code' => $shippingAddress->getRegionCode(),
                'province' => $shippingAddress->getRegion()
            ];
        }

        return $address;
    }

    /**
     * @param Shipment | Creditmemo $object
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getShipmentOrCreditmemoItems($object)
    {
        $items = [];
        foreach ($object->getItems() as $item) {
            $orderItem = $item->getOrderItem();
            $product = $orderItem->getProduct();
            if ($orderItem->getParentItemId() && isset($items[$orderItem->getParentItemId()]['bundle_items'])) {
                $items[$orderItem->getParentItemId()]['bundle_items'][] = [
                    'title' => $item->getName(),
                    'image' => $this->getProductImage($product),
                    'product_id' => $orderItem->getProductId(),
                    'sku' => $orderItem->getSku(),
                    'quantity' => $item->getQty(),
                    'price' => (float)$item->getPrice()
                ];

                continue;
            }

            if ($orderItem->getParentItemId() && isset($items[$orderItem->getParentItemId()])) {
                $items[$orderItem->getParentItemId()]['variant_title'] = $item->getName();
                $items[$orderItem->getParentItemId()]['variant_image'] = $this->getProductImage($product);
                $items[$orderItem->getParentItemId()]['variant_id'] = $orderItem->getProductId();
                $items[$orderItem->getParentItemId()]['variant_price'] = (float)$item->getPrice();

                continue;
            }

            $productType = $orderItem->getData('product_type');
            $items[$orderItem->getId()] = [
                'type' => $productType,
                'title' => $item->getName(),
                'price' => (float)$item->getPrice(),
                'quantity' => $item->getQty(),
                'sku' => $item->getSku(),
                'product_id' => $item->getProductId(),
                'image' => $this->getProductImage($product),
                'frontend_link' => $product->getProductUrl()
            ];

            if ($productType === 'bundle') {
                $items[$orderItem->getId()]['bundle_items'] = [];
            }
        }

        /**
         * Reformat data to compatible with API
         */
        $data = [];
        foreach ($items as $item) {
            $data[] = $item;
        }

        return $data;
    }

    /**
     * @param Quote|Shipment|Creditmemo|Order $object
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCartItems($object)
    {
        $items = [];
        $isQuote = $object instanceof Quote;

        foreach ($object->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            /**
             * @var Product $product
             */
            $product = $item->getProduct();
            $productType = $item->getData('product_type');

            $bundleItems = [];
            $hasVariant = $productType === 'configurable';
            $isBundle = $productType === 'bundle';

            $itemRequest = [
                'type' => $productType,
                'title' => $item->getName(),
                'price' => (float)$item->getPrice(),
                'quantity' => (int)($isQuote ? $item->getQty() : $item->getQtyOrdered()),
                'sku' => $item->getSku(),
                'product_id' => $item->getProductId(),
                'image' => $this->getProductImage($product),
                'frontend_link' => $product->getProductUrl()
            ];

            if ($isQuote) {
                $itemRequest['line_price'] = $item->getRowTotal();
            }

            if ($item->getHasChildren()) {
                $children = $isQuote ? $item->getChildren() : $item->getChildrenItems();
                foreach ($children as $child) {
                    $product = $child->getProduct();
                    if ($hasVariant) {
                        $itemRequest['variant_title'] = $child->getName();
                        $itemRequest['variant_image'] = $this->getProductImage($product);
                        $itemRequest['variant_id'] = $child->getProductId();
                        $itemRequest['variant_price'] = (float)$child->getPrice();
                    }

                    if ($isBundle) {
                        $bundleItems[] = [
                            'title' => $child->getName(),
                            'image' => $this->getProductImage($product),
                            'product_id' => $child->getProductId(),
                            'sku' => $child->getSku(),
                            'quantity' => (int)($isQuote ? $child->getQty() : $child->getQtyOrdered()),
                            'price' => (float)$child->getPrice()
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
     * @param Product $product
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getProductImage($product)
    {
        $image = $product->getSmallImage();
        if (!$image) {
            return 'https://cdn1.avada.io/email-marketing/placeholder-image.gif';
        }

        if ($image[0] !== '/') {
            $image = '/' . $image;
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $baseUrl . 'catalog/product' . $image;

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
        $body = $this->_curl->getBody();
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
     * @throws NoSuchEntityException
     */
    public function setHeaders($data, $url = '', $appID = '', $secretKey = '', $isTest = false)
    {
        if (!$url) {
            $url = self::APP_URL;
        }

        $this->url = $url;

        $body = self::jsonEncode(['data' => $data]);
        $storeId = $this->storeId ?: $this->getStoreId();
        $secretKey = $secretKey ?: $this->getSecretKey($storeId);
        $generatedHash = base64_encode(hash_hmac('sha256', $body, $secretKey, true));
        $appID = $appID ?: $this->getAppID($storeId);
        $this->_curl->addHeader('Content-Type', 'application/json');
        $this->_curl->addHeader('X-EmailMarketing-Hmac-Sha256', $generatedHash);
        $this->_curl->addHeader('X-EmailMarketing-App-Id', $appID);
        $this->_curl->addHeader('X-EmailMarketing-Connection-Test', $isTest);

        return $body;
    }

    /**
     * @return int|string
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        if (!$this->storeId) {
            return $this->storeManager->getStore()->getId();
        }

        return $this->storeId;
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
     * @param int $id
     * @param int $storeId
     */
    public function deleteQuote($id, $storeId)
    {
        $url = self::DELETE_URL . $id;
        $secretKey = $this->getSecretKey($storeId);
        $generatedHash = base64_encode(hash_hmac('sha256', '', $secretKey, true));
        $appID = $this->getAppID($storeId);
        $this->_curl->addHeader('Content-Type', 'application/json');
        $this->_curl->addHeader('X-EmailMarketing-Hmac-Sha256', $generatedHash);
        $this->_curl->addHeader('X-EmailMarketing-App-Id', $appID);

        /**
         * Remove logic post request and use delete request
         */
        $this->_curl->setOption(CURLOPT_POST, null);
        $this->_curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->_curl->post($url, []);
    }

    /**
     * @param int | string $subscriberStatus
     *
     * @return bool
     */
    public function isSubscriber($subscriberStatus)
    {
        return (int)$subscriberStatus === Subscriber::STATUS_SUBSCRIBED;
    }

    /**
     * @param Customer $customer
     * @param bool $isLoadSubscriber
     * @param bool $isUpdateOrder
     *
     * @return array
     * @throws LocalizedException
     */
    public function getCustomerData(Customer $customer, $isLoadSubscriber = false, $isUpdateOrder = false)
    {

        if ($isLoadSubscriber) {
            $subscriber = $this->_subscriberFactory->create()->loadByEmail($customer->getEmail());
            $isSubscriber = $this->isSubscriber($subscriber->getSubscriberStatus());
        } else {
            $subscriberStatus = $customer->getData('subscriber_status');
            $isSubscriber = $this->isSubscriber($subscriberStatus) ?: !!$customer->getIsSubscribed();
        }

        $data = [
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'phoneNumber' => '',
            'description' => '',
            'isSubscriber' => $isSubscriber,
            'source' => 'Magento',
        ];

        if ($isUpdateOrder) {
            $orderCollectionByCustomer = $this->orderCollection->addFieldToFilter('customer_id', $customer->getId());
            $size = $orderCollectionByCustomer->getSize();
            $lastOrderId = $orderCollectionByCustomer->addOrder('entity_id')->getFirstItem()->getId();
            $collection = $this->reportCollectionFactory->create()->calculateSales();
            $collection->addFieldToFilter('customer_id', $customer->getId())->load();
            $totalSpent = $this->formatCurrency($collection->getFirstItem()->getLifetime(), $customer->getWebsiteId());

            $data['orders_count'] = $size;
            $data['last_order_id'] = $lastOrderId;
            $data['total_spent'] = $totalSpent;
            $data['currency'] = $this->getBaseCurrencyByWebsiteId($customer->getWebsiteId())->getCurrencyCode();
        }

        return $data;
    }

    /**
     * @param int $id
     *
     * @return Customer
     */
    public function getCustomerById($id)
    {
        return $this->customerFactory->create()->load($id);
    }

    /**
     * @param int $customerId
     */
    public function updateCustomer($customerId)
    {
        if ($customerId) {
            try {
                $customer = $this->getCustomerById($customerId);
                $customerData = $this->getCustomerData($customer, true, true);
                $this->syncCustomer($customerData, false);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }

    /**
     * Format price by specified website
     *
     * @param float $price
     * @param null|int $websiteId
     *
     * @return string
     * @throws LocalizedException
     */
    public function formatCurrency($price, $websiteId = null)
    {
        return $this->getBaseCurrencyByWebsiteId($websiteId)->format($price, [], false);
    }

    /**
     * @param null|int $websiteId
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getBaseCurrencyByWebsiteId($websiteId)
    {
        return $this->storeManager->getWebsite($websiteId)->getBaseCurrency();
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
     * @param bool $isCreate
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function syncCustomer($data, $isCreate = true)
    {
        $url = $isCreate ? self::CUSTOMER_URL : self::CUSTOMER_UPDATE_URL;

        return $this->sendRequest($data, $url);
    }

    /**
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     */
    public function syncCustomers($data)
    {
        return $this->sendRequest($data, self::SYNC_CUSTOMER_URL);
    }

    /**
     * @return Attribute|string
     * @throws LocalizedException
     */
    public function getSyncedAttribute()
    {
        $attribute = self::IS_SYNCED_ATTRIBUTE;
        $attribute = $this->customerAttribute->loadByCode('customer', $attribute);
        if (!$attribute->getId()) {
            throw new LocalizedException(__('%1 not found.', $attribute));
        }

        return $attribute;
    }

    /**
     * @param boolean $value
     */
    public function setIsSyncedCustomer($value)
    {
        $this->isSyncCustomer = $value;
    }

    /**
     * @return boolean
     */
    public function isSyncedCustomer()
    {
        return $this->isSyncCustomer;
    }
}
