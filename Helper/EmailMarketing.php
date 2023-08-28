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
use IntlDateFormatter;
use Magento\Bundle\Helper\Catalog\Product\Configuration as BundleConfiguration;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Product\Configuration as CatalogConfiguration;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Address\Config;
use Magento\Customer\Model\Attribute;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\GroupFactory;
use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\Region;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Quote\Model\ResourceModel\Quote as ResourceQuote;
use Magento\Reports\Model\ResourceModel\Order\CollectionFactory as ReportOrderCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Store\Model\Information;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Model\Config\Source\DaysRange;
use Mageplaza\Smtp\Model\ResourceModel\AbandonedCart\Grid\Collection;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;
use Zend_Db_Select_Exception;

/**
 * Class EmailMarketing
 * @package Mageplaza\Smtp\Helper
 */
class EmailMarketing extends Data
{
    const CACHE_TAG           = 'mp_smtp_script';
    const IS_SYNCED_ATTRIBUTE = 'mp_smtp_is_synced';
    const API_URL             = 'https://app.avada.io';
    const APP_URL             = self::API_URL . '/app/api/v1/connects';
    const CHECKOUT_URL        = self::API_URL . '/app/api/v1/checkouts';
    const CUSTOMER_URL        = self::API_URL . '/app/api/v1/customers';
    const ORDER_URL           = self::API_URL . '/app/api/v1/orders';
    const ORDER_COMPLETE_URL  = self::API_URL . '/app/api/v1/orders/complete';
    const INVOICE_URL         = self::API_URL . '/app/api/v1/orders/invoice';
    const SHIPMENT_URL        = self::API_URL . '/app/api/v1/orders/ship';
    const CREDITMEMO_URL      = self::API_URL . '/app/api/v1/orders/refund';
    const DELETE_URL          = self::API_URL . '/app/api/v1/checkouts?id=';
    const SYNC_CUSTOMER_URL   = self::API_URL . '/app/api/v1/customers/bulk';
    const SYNC_ORDER_URL      = self::API_URL . '/app/api/v1/orders/bulk';
    const PROXY_URL           = self::API_URL . '/app/api/v1/proxy/';

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
     * @var TimezoneInterface
     */
    protected $_localeDate;

    /**
     * @var GroupFactory
     */
    protected $customerGroupFactory;

    /**
     * @var OrderConfig
     */
    protected $orderConfig;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var Config
     */
    protected $_addressConfig;

    /**
     * @var null
     */
    protected $_salesAmountExpression = null;

    /**
     * @var Information
     */
    protected $storeInfo;

    /**
     * @var StoreFactory
     */
    protected $storeFactory;

    /**
     * @var CountryFactory
     */
    protected $countryFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Region
     */
    protected $region;

    /**
     * @var Collection
     */
    protected $abandonedCartCollection;

    /**
     * @var ItemFactory
     */
    protected $quoteItemFactory;

    /**
     * @var ComponentRegistrarInterface
     */
    protected $componentRegistrar;

    /**
     * @var ReadFactory
     */
    protected $readFactory;

    /**
     * @var bool
     */
    protected $isPUT = false;

    /**
     * @var string
     */
    protected $smtpVersion = '';

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var Configurable
     */
    protected $configurable;

    /**
     * @var Grouped
     */
    protected $grouped;

    /**
     * @var Bundle
     */
    protected $bundle;

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
     * @param CatalogHelper $catalogHelper
     * @param EncryptorInterface $encryptor
     * @param CurlFactory $curlFactory
     * @param ProductRepository $productRepository
     * @param ResourceQuote $resourceQuote
     * @param ShippingHelper $shippingHelper
     * @param Attribute $customerAttribute
     * @param OrderCollection $orderCollection
     * @param ReportOrderCollectionFactory $reportCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param SubscriberFactory $subscriberFactory
     * @param GroupFactory $groupFactory
     * @param OrderConfig $orderConfig
     * @param TimezoneInterface $localeDate
     * @param Config $addressConfig
     * @param LoggerInterface $logger
     * @param Information $storeInfo
     * @param StoreFactory $storeFactory
     * @param CountryFactory $countryFactory
     * @param ResourceConnection $resourceConnection
     * @param Region $region
     * @param Collection $abandonedCartCollection
     * @param ItemFactory $quoteItemFactory
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ReadFactory $readFactory
     * @param CategoryFactory $categoryFactory
     * @param Configurable $configurable
     * @param Grouped $grouped
     * @param Bundle $bundle
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        UrlInterface $frontendUrl,
        Escaper $escaper,
        CatalogConfiguration $catalogConfiguration,
        BundleConfiguration $bundleProductConfiguration,
        CatalogHelper $catalogHelper,
        EncryptorInterface $encryptor,
        CurlFactory $curlFactory,
        ProductRepository $productRepository,
        ResourceQuote $resourceQuote,
        ShippingHelper $shippingHelper,
        Attribute $customerAttribute,
        OrderCollection $orderCollection,
        ReportOrderCollectionFactory $reportCollectionFactory,
        CustomerFactory $customerFactory,
        SubscriberFactory $subscriberFactory,
        GroupFactory $groupFactory,
        OrderConfig $orderConfig,
        TimezoneInterface $localeDate,
        Config $addressConfig,
        LoggerInterface $logger,
        Information $storeInfo,
        StoreFactory $storeFactory,
        CountryFactory $countryFactory,
        ResourceConnection $resourceConnection,
        Region $region,
        Collection $abandonedCartCollection,
        ItemFactory $quoteItemFactory,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        CategoryFactory $categoryFactory,
        Configurable $configurable,
        Grouped $grouped,
        Bundle $bundle
    ) {
        $this->frontendUrl                = $frontendUrl;
        $this->escaper                    = $escaper;
        $this->productConfig              = $catalogConfiguration;
        $this->bundleProductConfiguration = $bundleProductConfiguration;
        $this->catalogHelper              = $catalogHelper;
        $this->encryptor                  = $encryptor;
        $this->curlFactory                = $curlFactory;
        $this->resourceQuote              = $resourceQuote;
        $this->productRepository          = $productRepository;
        $this->shippingHelper             = $shippingHelper;
        $this->customerAttribute          = $customerAttribute;
        $this->orderCollection            = $orderCollection;
        $this->reportCollectionFactory    = $reportCollectionFactory;
        $this->customerFactory            = $customerFactory;
        $this->logger                     = $logger;
        $this->_subscriberFactory         = $subscriberFactory;
        $this->customerGroupFactory       = $groupFactory;
        $this->orderConfig                = $orderConfig;
        $this->_localeDate                = $localeDate;
        $this->_addressConfig             = $addressConfig;
        $this->storeInfo                  = $storeInfo;
        $this->storeFactory               = $storeFactory;
        $this->countryFactory             = $countryFactory;
        $this->resourceConnection         = $resourceConnection;
        $this->region                     = $region;
        $this->abandonedCartCollection    = $abandonedCartCollection;
        $this->quoteItemFactory           = $quoteItemFactory;
        $this->componentRegistrar         = $componentRegistrar;
        $this->readFactory                = $readFactory;
        $this->categoryFactory            = $categoryFactory;
        $this->configurable               = $configurable;
        $this->grouped                    = $grouped;
        $this->bundle                     = $bundle;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @return Curl
     */
    public function initCurl()
    {
        $this->_curl = $this->curlFactory->create();

        if ($this->isPUT) {
            $this->_curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
            $this->isPUT = false;
        }

        return $this->_curl;
    }

    /**
     * @param bool $flag
     */
    public function setIsUpdateRequest($flag)
    {
        $this->isPUT = $flag;
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
        if ($item->getProductType() === Type::TYPE_BUNDLE) {
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
            'max_length'   => 55,
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
                $customerName = explode('@', $quote->getCustomerEmail() ?: '')[0];
            }
        }

        return $this->escaper->escapeHtml($customerName);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getDefineVendor($storeId = null)
    {
        return $this->getEmailMarketingConfig('define_vendor', $storeId);
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
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConnectToken($storeId = null)
    {
        $token = $this->getEmailMarketingConfig('connectToken', $storeId);

        return $this->encryptor->decrypt($token);
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
     * @param string|null $date
     *
     * @return string
     * @throws LocalizedException
     */
    public function formatDate($date)
    {
        $date = $this->_localeDate->convertConfigTimeToUtc($date);

        return $this->_localeDate->formatDateTime(
            $date,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::MEDIUM,
            null,
            null,
            DateTime::DATETIME_INTERNAL_FORMAT
        );
    }

    /**
     * @param Shipment|Creditmemo|Order|Invoice $object
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getOrderData($object)
    {
        $data = [
            'id'             => (int) $object->getId(),
            'name'           => '#' . $object->getIncrementId(),
            'shipping_price' => $object->getShippingAmount(),
            'currency'       => $object->getBaseCurrencyCode(),
            'order_currency' => $object->getOrderCurrencyCode(),
            'is_utc'         => true,
            'created_at'     => $this->formatDate($object->getCreatedAt()),
            'updated_at'     => $this->formatDate($object->getUpdatedAt()),
            'timezone'       => $this->_localeDate->getConfigTimezone(
                ScopeInterface::SCOPE_STORE,
                $object->getStoreId()
            )
        ];

        $path              = null;
        $customerEmail     = $object->getCustomerEmail();
        $customerId        = $object->getCustomerId();
        $customerFirstname = $object->getCustomerFirstname();
        $customerLastname  = $object->getCustomerLastname();
        $isShipment        = $object instanceof Shipment;
        $isCreditmemo      = $object instanceof Creditmemo;
        $isInvoice         = $object instanceof Invoice;
        if ($isCreditmemo || $isShipment || $isInvoice) {
            $order                  = $object->getOrder();
            $customerEmail          = $order->getCustomerEmail();
            $customerId             = $order->getCustomerId();
            $customerFirstname      = $order->getCustomerFirstname();
            $customerLastname       = $order->getCustomerLastname();
            $data['order_id']       = $object->getOrderId();
            $data['shipping_price'] = $order->getShippingAmount();

            $path = 'sales/order/creditmemo';
            if ($isShipment) {
                $path = 'sales/order/shipment';
            }
        }

        $data['email']    = $customerEmail;
        $data['customer'] = [
            'id'         => $customerId,
            'email'      => $customerEmail,
            'first_name' => $customerFirstname ?: '',
            'last_name'  => $customerLastname ?: '',
            'telephone'  => $object->getBillingAddress() ? $object->getBillingAddress()->getTelephone() : '',
            'tags'       => $this->getTags($this->customerFactory->create()->load($customerId))
        ];

        $shippingAddress = $object->getShippingAddress();

        if ($shippingAddress) {
            $data['shipping_address'] = $this->getDataAddress($shippingAddress);
        }

        $billingAddress = $object->getBillingAddress();

        if ($billingAddress) {
            $data['billing_address'] = $this->getDataAddress($billingAddress);
        }

        if (!$isInvoice) {
            $data['order_status_url'] = $this->getOrderViewUrl($object->getStoreId(), $object->getId(), $path);
        }

        if ($isShipment) {
            if ($object->getData('tracks')) {
                $data['trackingUrl'] = $this->shippingHelper->getTrackingPopupUrlBySalesModel($object);
                $tracks              = [];
                foreach ($object->getData('tracks') as $track) {
                    $tracks[] = [
                        'company' => $track->getTitle(),
                        'number'  => $track->getTrackNumber(),
                        'url'     => $this->shippingHelper->getTrackingPopupUrlBySalesModel($track)
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
                    'last_name'  => $shippingAddress->getLastname(),
                    'address1'   => $shippingAddress->getStreetLine(1),
                    'city'       => $shippingAddress->getCity(),
                    'zip'        => $shippingAddress->getPostcode(),
                    'country'    => $shippingAddress->getCountryId(),
                    'phone'      => $shippingAddress->getTelephone()
                ];
            }
        }

        if ($isShipment || $isCreditmemo || $isInvoice) {
            $data['line_items'] = $this->getShipmentOrCreditmemoItems($object);
        } else {
            $data['line_items'] = $this->getCartItems($object);
        }

        if ($object instanceof Order) {
            $payment      = $object->getPayment();
            $paymentTitle = '';
            if ($payment && $payment->getMethodInstance()) {
                $paymentTitle = $payment->getMethodInstance()->getTitle();
            }

            $data['gateway']             = $paymentTitle;
            $data['status']              = $object->getStatus();
            $data['state']               = $object->getState();
            $data['total_price']         = $object->getBaseGrandTotal();
            $data['subtotal_price']      = $object->getBaseSubtotal();
            $data['total_tax']           = $object->getBaseTaxAmount();
            $data['total_weight']        = $object->getTotalWeight() ?: '0';
            $data['total_shipping_cost'] = $object->getBaseShippingAmount();
            $data['total_discounts']     = $object->getBaseDiscountAmount();
        }

        return $data;
    }

    /**
     * @param Object $object
     *
     * @return array
     */
    public function getDataAddress($object)
    {
        return [
            'first_name'    => $object->getFirstname(),
            'last_name'     => $object->getLastname(),
            'address1'      => $object->getStreetLine(1),
            'city'          => $object->getCity(),
            'zip'           => $object->getPostcode(),
            'country'       => $object->getCountryId(),
            'phone'         => $object->getTelephone(),
            'province'      => $object->getRegion(),
            'address2'      => $object->getStreetLine(2) . ' ' . $object->getStreetLine(3),
            'company'       => $object->getCompany(),
            'latitude'      => '',
            'longitude'     => '',
            'name'          => $object->getFirstname() . $object->getLastname(),
            'country_code'  => $object->getCountryId(),
            'province_code' => ''
        ];
    }

    /**
     * @param Shipment|Creditmemo|Order $object
     * @param string $url
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function sendOrderRequest($object, $url = '')
    {
        $data = $this->getOrderData($object);

        if (!$url) {
            $data['checkout_id'] = $object->getQuoteId();
            $url                 = self::ORDER_URL;
        }
        $this->storeId = $object->getStoreId();
        $this->sendRequest($data, $url);
    }

    /**
     * @param array $data
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function updateOrderStatusRequest($data)
    {
        $this->setIsUpdateRequest(true);

        return $this->sendRequest($data, self::ORDER_URL);
    }

    /**
     * @param int $storeId
     * @param int $orderId
     * @param string|null $path
     *
     * @return string
     */
    public function getOrderViewUrl($storeId, $orderId, $path = null)
    {
        $this->frontendUrl->setScope($storeId);

        return $this->frontendUrl->getUrl($path ?? 'sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * @param Quote $quote
     * @param array|null $address
     * @param boolean $isOsc
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getACEData($quote, array $address = null, $isOsc = false)
    {
        $isActive         = (bool) $quote->getIsActive();
        $quoteCompletedAt = null;
        $updatedAt        = $this->getQuoteUpdatedAt($quote->getId());

        //first time created is the same updated
        $createdAt = $quote->getCreatedAt() ?: $updatedAt;
        $createdAt = $this->formatDate($createdAt);
        $updatedAt = $this->formatDate($updatedAt);
        if (!$isActive) {
            $quoteCompletedAt = $updatedAt;
        }

        return [
            'id'                     => (int) $quote->getId(),
            'email'                  => $quote->getCustomerEmail(),
            'completed_at'           => $quoteCompletedAt,
            'timezone'               => $this->_localeDate->getConfigTimezone(
                ScopeInterface::SCOPE_STORE,
                $quote->getStoreId()
            ),
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
            'is_utc'                 => true,
            'created_at'             => $createdAt,
            'updated_at'             => $updatedAt,
            'abandoned_checkout_url' => $this->getRecoveryUrl($quote),
            'subtotal_price'         => (float) $quote->getBaseSubtotal(),
            'total_price'            => (float) $quote->getData('base_grand_total'),
            'total_tax'              => !$quote->isVirtual() ? $quote->getShippingAddress()->getBaseTaxAmount() : 0,
            'customer_locale'        => null,
            'shipping_address'       => $this->getShippingAddress($quote, $address),
            'billing_address'        => $this->getBillingAddress($quote, $address, $isOsc)
        ];
    }

    /**
     * @param Quote $quote
     * @param array|null $address
     *
     * @return array
     */
    public function getShippingAddress(Quote $quote, $address = null)
    {
        return $this->getAddress($quote, $quote->getShippingAddress(), $address, 'shippingAddress');
    }

    /**
     * @param Quote $quote
     * @param array|null $address
     * @param boolean $isOsc
     *
     * @return array
     */
    public function getBillingAddress(Quote $quote, $address = null, $isOsc = false)
    {
        $field         = 'billingAddress';
        $paymentMethod = $quote->getPayment()->getMethod();

        if ($paymentMethod) {
            if (!$isOsc) {
                $field .= $paymentMethod;
            }

            return $this->getAddress($quote, $quote->getBillingAddress(), $address, $field);
        }

        return [];
    }

    /**
     * @param Quote $quote
     * @param Address $addressObject
     * @param array|null $addr
     * @param string $field
     *
     * @return array
     */
    public function getAddress(Quote $quote, Address $addressObject, $addr, $field)
    {
        $result = [];

        if (!$quote->isVirtual() && $addressObject) {
            $result = [
                'name'          => (isset($addr[$field . '.firstname']) || isset($addr[$field . '.lastname']))
                    ? $addr[$field . '.firstname'] . ' ' . $addr[$field . '.lastname']
                    : $addressObject->getName(),
                'last_name'     => $addr[$field . '.lastname'] ?? $addressObject->getLastname(),
                'phone'         => $addr[$field . '.telephone'] ?? $addressObject->getTelephone(),
                'company'       => $addr[$field . '.company'] ?? $addressObject->getCompany(),
                'country_code'  => $addr[$field . '.country_id'] ?? $addressObject->getCountryId(),
                'zip'           => $addr[$field . '.postcode'] ?? $addressObject->getPostcode(),
                'address1'      => $addr[$field . '.street.0'] ?? $addressObject->getStreetLine(1),
                'address2'      => $addr[$field . '.street.1'] ?? $addressObject->getStreetLine(2),
                'city'          => $addr[$field . '.city'] ?? $addressObject->getCity(),
                'province_code' => isset($addr[$field . '.region_id'])
                    ? $this->region->load($addr[$field . '.region_id'])->getCode()
                    : $addressObject->getRegionCode(),
                'province'      => isset($addr[$field . '.region_id'])
                    ? $this->region->load($addr[$field . '.region_id'])->getName()
                    : $addressObject->getRegion()
            ];
        }

        return $result;
    }

    /**
     * @param Shipment|Creditmemo $object
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getShipmentOrCreditmemoItems($object)
    {
        $items = [];
        foreach ($object->getItems() as $item) {
            /** @var OrderItem $orderItem */
            $orderItem = $item->getOrderItem();
            $product   = $this->getProductFromItem($orderItem);
            $createdAt = $item->getCreatedAt();
            $updatedAt = $item->getUpdatedAt();

            if (!$createdAt) {
                $createdAt = $object->getCreatedAt();
            }

            if (!$updatedAt) {
                $updatedAt = $object->getUpdatedAt();
            }

            if ($orderItem->getParentItemId() && isset($items[$orderItem->getParentItemId()]['bundle_items'])) {
                $items[$orderItem->getParentItemId()]['bundle_items'][] = [
                    'title'      => $item->getName(),
                    'name'       => $item->getName(),
                    'image'      => $this->getProductImage($product),
                    'product_id' => $orderItem->getProductId(),
                    'sku'        => $orderItem->getSku(),
                    'quantity'   => $item->getQty(),
                    'price'      => (float) $item->getBasePrice(),
                    'is_utc'     => true,
                    'created_at' => $this->formatDate($createdAt),
                    'updated_at' => $this->formatDate($updatedAt),
                    'categories' => $this->getCategories($product->getCategoryIds())
                ];

                continue;
            }

            if ($orderItem->getParentItemId() && isset($items[$orderItem->getParentItemId()])) {
                $items[$orderItem->getParentItemId()]['variant_title'] = $item->getName();
                $items[$orderItem->getParentItemId()]['variant_image'] = $this->getProductImage($product);
                $items[$orderItem->getParentItemId()]['variant_id']    = $orderItem->getProductId();
                $items[$orderItem->getParentItemId()]['variant_price'] = (float) $item->getBasePrice();

                continue;
            }

            $productType = $orderItem->getData('product_type');
            $isBundle    = $productType === Type::TYPE_BUNDLE;
            $sku         = $isBundle ? $product->getSku() : $item->getData('sku');
            $products    = $this->getProductBySku($orderItem, $sku);

            $items[$orderItem->getId()] = [
                'type'          => $productType,
                'name'          => $productType === Configurable::TYPE_CODE ?
                    $this->getItemOptions($orderItem) : $item->getName(),
                'title'         => $item->getName(),
                'price'         => (float) $item->getBasePrice(),
                'quantity'      => $item->getQty(),
                'sku'           => $item->getSku(),
                'product_id'    => $item->getProductId(),
                'image'         => $this->getProductImage($product),
                'frontend_link' => $products->getProductUrl() ?: ($product->getProductUrl() ?: '#'),
                'is_utc'        => true,
                'created_at'    => $this->formatDate($createdAt),
                'updated_at'    => $this->formatDate($updatedAt),
                'categories'    => $this->getCategories($products->getCategoryIds())
            ];

            if ($productType === Type::TYPE_BUNDLE) {
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
     * @param Item $item
     *
     * @return string
     */
    public function getOptionsWithName($item)
    {
        $options = $this->getProductOptions($item);

        return $this->formatOptions($item, $options);
    }

    /**
     * @param Item $item
     * @param array $options
     *
     * @return string
     */
    public function formatOptions($item, $options)
    {
        $name = $item->getName();
        foreach ($options as $option) {
            $name .= ' ' . $option['label'] . ':' . $option['value'];
        }

        return $name;
    }

    /**
     * @param OrderItem|Item $orderItem
     *
     * @return string
     */
    public function getItemOptions($orderItem)
    {
        $result  = [];
        $options = $orderItem->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }

        return $this->formatOptions($orderItem, $result);
    }

    /**
     * @param Item|OrderItem $item
     *
     * @return DataObject|Product
     */
    public function getProductFromItem($item)
    {
        return $item->getProduct() ?: ($this->getProductById($item->getProductId()) ?: new DataObject([]));
    }

    /**
     * @param int $productId
     *
     * @return ProductInterface|mixed|null
     */
    public function getProductById($productId)
    {
        try {
            /** @var Product $product */
            return $this->productRepository->getById($productId);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param Quote|Shipment|Creditmemo|Order $object
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCartItems($object)
    {
        $items   = [];
        $isQuote = $object instanceof Quote;

        foreach ($object->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $createdAt = $item->getCreatedAt();
            $updatedAt = $item->getUpdatedAt();
            if ($isQuote && (!$createdAt || !$updatedAt)) {
                $quoteItem = $this->quoteItemFactory->create()->load($item->getItemId(), 'item_id');
                $createdAt = $quoteItem->getCreatedAt();
                $updatedAt = $quoteItem->getUpdatedAt();
            }

            /**
             * @var Product $product
             */
            $product     = $this->getProductFromItem($item);
            $productType = $item->getData('product_type');

            $bundleItems = [];
            $hasVariant  = $productType === Configurable::TYPE_CODE;
            $isBundle    = $productType === Type::TYPE_BUNDLE;
            $name        = $item->getName();
            if ($hasVariant) {
                if ($isQuote) {
                    $name = $this->getOptionsWithName($item);
                } else {
                    $name = $this->getItemOptions($item);
                }
            }

            $sku      = $isBundle ? $product->getSku() : $item->getData('sku');
            $products = $this->getProductBySku($item, $sku);

            if (is_object($products->getCustomAttribute($this->getDefineVendor()))) {
                $vendorValue = $products->getAttributeText($this->getDefineVendor());
            } else {
                $vendorValue = '';
            }

            $itemRequest = [
                'type'          => $productType,
                'title'         => $item->getName(),
                'name'          => $name,
                'price'         => (float) $item->getBasePrice(),
                'tax_price'     => (float) ($isQuote ?
                    $item->getBaseTaxAmount() / $item->getQty() : $item->getBaseTaxAmount() / $item->getQtyOrdered()),
                'quantity'      => (int) ($isQuote ? $item->getQty() : $item->getQtyOrdered()),
                'sku'           => $item->getSku(),
                'product_id'    => $item->getProductId(),
                'image'         => $this->getProductImage($product),
                'frontend_link' => $products->getProductUrl() ?: ($product->getProductUrl() ?: '#'),
                'vendor'        => $vendorValue,
                'is_utc'        => true,
                'created_at'    => $this->formatDate($createdAt),
                'updated_at'    => $this->formatDate($updatedAt),
                'categories'    => $this->getCategories($products->getCategoryIds())
            ];

            if ($isQuote) {
                $itemRequest['line_price'] = (float) $item->getBaseRowTotal();
            }

            if ($item->getHasChildren()) {
                $children = $isQuote ? $item->getChildren() : $item->getChildrenItems();
                foreach ($children as $child) {
                    $product = $this->getProductFromItem($child);
                    if ($hasVariant) {
                        $itemRequest['variant_title'] = $child->getName();
                        $itemRequest['variant_image'] = $this->getProductImage($product);
                        $itemRequest['variant_id']    = $child->getProductId();
                        $itemRequest['variant_price'] = (float) $child->getBasePrice();
                    }

                    if ($isBundle) {
                        $bundleItems[] = [
                            'title'      => $child->getName(),
                            'name'       => $child->getName(),
                            'image'      => $this->getProductImage($product),
                            'product_id' => $child->getProductId(),
                            'sku'        => $child->getSku(),
                            'quantity'   => (int) ($isQuote ? $child->getQty() : $child->getQtyOrdered()),
                            'price'      => (float) $child->getBasePrice(),
                            'is_utc'     => true,
                            'created_at' => $this->formatDate($createdAt),
                            'updated_at' => $this->formatDate($updatedAt),
                            'categories' => $this->getCategories($product->getCategoryIds())
                        ];
                    }
                }
            }

            $itemRequest['bundle_items'] = $bundleItems;
            $items[]                     = $itemRequest;
        }

        return $items;
    }

    /**
     * @param Product $product
     *
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

        $baseUrl  = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $baseUrl . 'catalog/product' . $image;

        return str_replace('\\', '/', $imageUrl);
    }

    /**
     * @param array $data
     * @param string $appID
     * @param string $url
     * @param string $secretKey
     * @param bool $isTest
     * @param bool $isLog
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function sendRequest($data, $url = '', $appID = '', $secretKey = '', $isTest = false, $isLog = false)
    {
        $this->initCurl();
        $body = $this->setHeaders($data, $url, $appID, $secretKey, $isTest);
        $this->_curl->post($this->url, $body);
        $body     = $this->_curl->getBody();
        $bodyData = self::jsonDecode($body);

        if ($isLog) {
            return $bodyData;
        }

        if (!isset($bodyData['success']) || !$bodyData['success']) {
            throw new LocalizedException(__('Error : %1', isset($bodyData['message']) ? $bodyData['message'] : ''));
        }

        $this->_curl = '';

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

        $body          = self::jsonEncode(['data' => $data]);
        $storeId       = $this->storeId ?: $this->getStoreId();
        $secretKey     = $secretKey ?: $this->getSecretKey($storeId);
        $generatedHash = base64_encode(hash_hmac('sha256', $body, $secretKey, true));
        $appID         = $appID ?: $this->getAppID($storeId);
        $this->_curl->addHeader('Content-Type', 'application/json');
        $this->_curl->addHeader('X-EmailMarketing-Hmac-Sha256', $generatedHash);
        $this->_curl->addHeader('X-EmailMarketing-App-Id', $appID);
        $this->_curl->addHeader('X-EmailMarketing-Connection-Test', $isTest);
        $this->_curl->addHeader('X-EmailMarketing-Integration-Key', $this->getConnectToken($storeId));
        $this->_curl->addHeader('X-EmailMarketing-M2-Version', $this->getMagentoVersion());
        $this->_curl->addHeader('X-EmailMarketing-SMTP-Version', $this->getSMTPVersion());

        return $body;
    }

    /**
     * @return Phrase|string|void
     */
    public function getSMTPVersion()
    {
        if (!$this->smtpVersion) {
            $this->smtpVersion = $this->getModuleVersion('Mageplaza_Smtp');
        }

        return $this->smtpVersion;
    }

    /**
     * Get module composer version
     *
     * @param string $moduleName
     *
     * @return Phrase|string|void
     */
    public function getModuleVersion($moduleName)
    {
        try {
            $path             = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
            $directoryRead    = $this->readFactory->create($path);
            $composerJsonData = $directoryRead->readFile('composer.json');
            $data             = json_decode($composerJsonData);

            return !empty($data->version) ? $data->version : __('UNKNOWN');
        } catch (Exception $e) {
            return 'error';
        }
    }

    /**
     * Get Product version
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        $productMetadata = $this->objectManager->get(ProductMetadataInterface::class);

        return $productMetadata->getVersion();
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
            $this->initCurl();
            $body = $this->setHeaders($data, $url, $appID, $secretKey);
            $this->_curl->setOption(CURLOPT_TIMEOUT_MS, 500);
            $this->_curl->post($this->url, $body);
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());
            // Ignore exception timeout
        }
    }

    /**
     * @param int $id
     * @param int $storeId
     */
    public function deleteQuote($id, $storeId)
    {
        $this->initCurl();
        $url           = self::DELETE_URL . $id;
        $secretKey     = $this->getSecretKey($storeId);
        $generatedHash = base64_encode(hash_hmac('sha256', '', $secretKey, true));
        $appID         = $this->getAppID($storeId);
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
        return (int) $subscriberStatus === Subscriber::STATUS_SUBSCRIBED;
    }

    /**
     * @param Customer $customer
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getTags(Customer $customer)
    {
        $tags   = [];
        $tags[] = $this->storeManager->getStore($customer->getStoreId())->getName();
        $tags[] = $this->storeManager->getWebsite($customer->getWebsiteId())->getName();
        $tags[] = $this->customerGroupFactory->create()->load($customer->getGroupId())->getCustomerGroupCode();

        return implode(',', $tags);
    }

    /**
     * @param Customer $customer
     * @param bool $isLoadSubscriber
     * @param bool $isUpdateOrder
     * @param null $address
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCustomerData(
        Customer $customer,
        $isLoadSubscriber = false,
        $isUpdateOrder = false,
        $address = null
    ) {

        if ($isLoadSubscriber) {
            $subscriber   = $this->_subscriberFactory->create()->loadByEmail($customer->getEmail());
            $isSubscriber = $this->isSubscriber($subscriber->getSubscriberStatus());
        } else {
            $subscriberStatus = $customer->getData('subscriber_status');
            $isSubscriber     = $this->isSubscriber($subscriberStatus) ?: !!$customer->getIsSubscribed();
        }

        $data = [
            'id'            => (int) $customer->getId(),
            'email'         => $customer->getEmail(),
            'firstName'     => $customer->getFirstname(),
            'lastName'      => $customer->getLastname(),
            'phoneNumber'   => $address ? $address->getTelephone() : '',
            'gender'        => $customer->getGender(),
            'description'   => '',
            'isSubscriber'  => $isSubscriber,
            'tags'          => $this->getTags($customer),
            'source'        => 'Magento',
            'timezone'      => $this->_localeDate->getConfigTimezone(
                ScopeInterface::SCOPE_STORE,
                $customer->getStoreId()
            ),
            'customer_type' => 'new_customer',
            'dob'           => $customer->getDob() ? $this->formatDate($customer->getDob()) : '',
            'is_utc'        => true,
            'created_at'    => $this->formatDate($customer->getCreatedAt()),
            'updated_at'    => $this->formatDate($customer->getUpdatedAt())
        ];

        $defaultBillingAddress = $customer->getDefaultBillingAddress();
        if ($defaultBillingAddress) {
            $data['countryCode'] = $defaultBillingAddress->getCountryId();
            $country             = $this->countryFactory->create()->loadByCode($data['countryCode']);
            $data['country']     = $country->getName();
            $data['city']        = $defaultBillingAddress->getCity();
            $renderer            = $this->_addressConfig->getFormatByCode(ElementFactory::OUTPUT_FORMAT_ONELINE)
                ->getRenderer();
            $data['address']     = $renderer->renderArray($defaultBillingAddress->getData());
            $data['phoneNumber'] = $defaultBillingAddress->getTelephone();
        }

        if ($isUpdateOrder) {
            $orderCollectionByCustomer  = clone $this->orderCollection;
            $_orderCollectionByCustomer = $orderCollectionByCustomer->addFieldToFilter(
                'customer_id',
                $customer->getId()
            );
            $size                       = $_orderCollectionByCustomer->getSize();
            $lastOrderId                = $_orderCollectionByCustomer->addOrder('entity_id')->getFirstItem()->getId();

            $data['orders_count']  = $size;
            $data['last_order_id'] = $lastOrderId;
            $data['total_spent']   = $this->getLifetimeSales($customer->getId());
            $data['currency']      = $this->getBaseCurrencyByWebsiteId($customer->getWebsiteId())->getCurrencyCode();
        }

        return $data;
    }

    /**
     * @param int $customerId
     *
     * @return mixed
     */
    public function getLifetimeSales($customerId)
    {
        $statuses   = $this->orderConfig->getStateStatuses(Order::STATE_CANCELED);
        $collection = $this->reportCollectionFactory->create();
        $collection->setMainTable('sales_order');
        $collection->removeAllFieldsFromSelect();
        $collection->addFieldToFilter('customer_id', $customerId);
        $expr = $this->_getSalesAmountExpression($collection->getConnection());

        $expr = '(' . $expr . ') * main_table.base_to_global_rate';

        $collection->getSelect()->columns(
            ['lifetime' => "SUM({$expr})"]
        )->where(
            'main_table.status NOT IN(?)',
            $statuses
        )->where(
            'main_table.state NOT IN(?)',
            [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]
        );

        return $collection->getFirstItem()->getLifetime();
    }

    /**
     * @param AdapterInterface $connection
     *
     * @return string|null
     */
    protected function _getSalesAmountExpression($connection)
    {
        if (null === $this->_salesAmountExpression) {
            $expressionTransferObject = new DataObject(
                [
                    'expression' => '%s - %s - %s - (%s - %s - %s)',
                    'arguments'  => [
                        $connection->getIfNullSql('main_table.base_total_invoiced', 0),
                        $connection->getIfNullSql('main_table.base_tax_invoiced', 0),
                        $connection->getIfNullSql('main_table.base_shipping_invoiced', 0),
                        $connection->getIfNullSql('main_table.base_total_refunded', 0),
                        $connection->getIfNullSql('main_table.base_tax_refunded', 0),
                        $connection->getIfNullSql('main_table.base_shipping_refunded', 0),
                    ],
                ]
            );

            $this->_salesAmountExpression = vsprintf(
                $expressionTransferObject->getExpression(),
                $expressionTransferObject->getArguments()
            );
        }

        return $this->_salesAmountExpression;
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
                $customer     = $this->getCustomerById($customerId);
                $customerData = $this->getCustomerData($customer, true, true);
                $this->syncCustomer($customerData, false);
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
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
     *
     * @return mixed
     * @throws LocalizedException
     * @throws Zend_Db_Select_Exception
     */
    public function testConnection($appID, $secretKey)
    {
        if ($secretKey === '******') {
            $secretKey = $this->getSecretKey();
        }

        return $this->sendRequest($this->getStoreInformation(), '', $appID, $secretKey);
    }

    /**
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getStoreInformation()
    {
        $storeId   = $this->_request->getParam('store');
        $websiteId = $this->_request->getParam('website');
        $scopeCode = $storeId ?: $websiteId ?: null;

        if ($storeId) {
            $scope = ScopeInterface::SCOPE_STORES;
        } elseif ($websiteId) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
        } else {
            $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        }

        $info = [
            'name'          => $this->getConfigData(Information::XML_PATH_STORE_INFO_NAME, $scope, $scopeCode),
            'phone'         => $this->getConfigData(Information::XML_PATH_STORE_INFO_PHONE, $scope, $scopeCode),
            'countryCode'   => $this->getConfigData(Information::XML_PATH_STORE_INFO_COUNTRY_CODE, $scope, $scopeCode),
            'city'          => $this->getConfigData(Information::XML_PATH_STORE_INFO_CITY, $scope, $scopeCode),
            'timezone'      => $this->_localeDate->getConfigTimezone($scope, $scopeCode),
            'zip'           => $this->getConfigData(Information::XML_PATH_STORE_INFO_POSTCODE, $scope, $scopeCode),
            'currency'      => $this->getConfigData(Currency::XML_PATH_CURRENCY_BASE),
            'address1'      => $this->getConfigData(Information::XML_PATH_STORE_INFO_STREET_LINE1, $scope, $scopeCode),
            'address2'      => $this->getConfigData(Information::XML_PATH_STORE_INFO_STREET_LINE2, $scope, $scopeCode),
            'email'         => $this->getConfigData('trans_email/ident_general/email'),
            'contact_count' => $this->getContactCount() ?: 0,
            'order_count'   => $this->orderCollection->getSize(),
            'ace_count'     => $this->abandonedCartCollection->getSize()
        ];

        if ($info['countryCode']) {
            $info['countryName'] = $this->countryFactory->create()->loadByCode($info['countryCode'])->getName();
        }

        return $info;
    }

    /**
     * @param string $path
     * @param string $scope
     * @param null $scopeCode
     *
     * @return mixed
     */
    public function getConfigData($path, $scope = ScopeInterface::SCOPE_STORES, $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scope, $scopeCode);
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
        $this->setIsUpdateRequest(!$isCreate);

        return $this->sendRequest($data, self::CUSTOMER_URL);
    }

    /**
     * @param array $data
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function syncCustomers($data)
    {
        return $this->sendRequest($data, self::SYNC_CUSTOMER_URL, '', '', false, true);
    }

    /**
     * @param array $data
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function syncOrders($data)
    {
        return $this->sendRequest($data, self::SYNC_ORDER_URL, '', '', false, true);
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

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getSubscriberConfig($storeId = null)
    {
        return $this->getEmailMarketingConfig('newsletter_subscriber', $storeId);
    }

    /**
     * @param string $daysRange
     * @param string $from
     * @param string $to
     * @param string $pre
     *
     * @return string|Zend_Db_Expr
     */
    public function queryExpr($daysRange, $from = '', $to = '', $pre = 'main_table')
    {
        if ($daysRange !== DaysRange::CUSTOM) {
            $from = date('Y-m-d', time() - (int) $daysRange * 24 * 60 * 60);
        }

        $queryExpr = '';

        if ($from) {
            $queryExpr = new Zend_Db_Expr("DATE({$pre}.created_at) >= '{$from}'");
        }

        if ($to) {
            $queryExpr = new Zend_Db_Expr("DATE({$pre}.created_at) <= '{$to}'");
        }

        if ($from && $to) {
            $queryExpr = new Zend_Db_Expr("DATE({$pre}.created_at) >= '{$from}' AND DATE({$pre}.created_at) <= '{$to}'");
        }

        return $queryExpr;
    }

    /**
     * @return int
     * @throws Zend_Db_Select_Exception
     */
    public function getContactCount()
    {
        $connection      = $this->resourceConnection->getConnection();
        $subscriberTable = $this->resourceConnection->getTableName('newsletter_subscriber');
        $customerTable   = $this->resourceConnection->getTableName('customer_entity');

        $union = $connection->select()->union([
            $connection->select()->from($subscriberTable, 'subscriber_email'),
            $connection->select()->from($customerTable, 'email')
        ]);
        $query = $connection->select()->from($union, 'COUNT(*)');
        $count = $connection->fetchOne($query);

        return (int) $count;
    }

    /**
     * @param AdapterInterface $connection
     * @param array $ids
     * @param string $table
     * @param bool $subscriber
     *
     * @throws Exception
     */
    public function updateData($connection, $ids, $table, $subscriber = false)
    {
        $connection->beginTransaction();
        try {
            $where = [$subscriber ? 'subscriber_id' : 'entity_id' . ' IN (?)' => $ids];
            $connection->update(
                $table,
                ['mp_smtp_email_marketing_synced' => 1],
                $where
            );
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param string|null $storeId
     *
     * @return mixed
     */
    public function isTracking($storeId = null)
    {
        return $this->getEmailMarketingConfig('is_tracking', $storeId);
    }

    /**
     * @param string|null $storeId
     *
     * @return mixed
     */
    public function isPushNotification($storeId = null)
    {
        return $this->getEmailMarketingConfig('push_notification', $storeId);
    }

    /**
     * @param string $url
     * @param array|null $data
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function sendRequestProxy($url, $data)
    {
        $params = $this->_request->getParams();

        $this->initCurl();

        if ($this->_request->getMethod() === 'POST') {
            $body = $this->setHeaders($data, $url);
            $this->_curl->post($url, $body);
        } else {
            $this->_curl->get($url);
        }

        $body     = $this->_curl->getBody();
        $bodyData = self::jsonDecode($body);
        if (isset($params['type'])) {
            $bodyData = $body;
        }

        $this->_curl = '';

        return $bodyData;
    }

    /**
     * @param array|null $categoryIds
     *
     * @return array
     */
    public function getCategories($categoryIds)
    {
        $categories = [];

        if ($categoryIds) {
            foreach ($categoryIds as $categoryId) {
                /** @var Category $category */
                $category     = $this->categoryFactory->create()->load($categoryId);
                $categories[] = [
                    'category_id'   => $category->getId(),
                    'category_name' => $category->getName()
                ];
            }
        }

        return $categories;
    }

    /**
     * @param int $childId
     *
     * @return mixed|null
     */
    public function getParentId($childId)
    {
        $productId = null;
        /* for simple product of configurable product */
        $parentIds = $this->configurable->getParentIdsByChild($childId);

        if (isset($parentIds[0])) {
            return $parentIds[0];
        }

        /* for simple product of Group product */
        $parentIds = $this->grouped->getParentIdsByChild($childId);

        if (isset($parentIds[0])) {
            return $parentIds[0];
        }

        $parentIds = $this->bundle->getParentIdsByChild($childId);

        if (isset($parentIds[0])) {
            return $parentIds[0];
        }

        return $productId;
    }

    /**
     * @param OrderItem $item
     * @param string $sku
     *
     * @return ProductInterface|Product|DataObject|mixed|null
     */
    public function getProductBySku($item, $sku)
    {
        try {
            $products   = $this->productRepository->get($sku);
            $buyRequest = $item->getBuyRequest();
            if ($buyRequest && (int) $products->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
                if ($buyRequest->getData('super_product_config')) {
                    $productId = $buyRequest->getData('super_product_config')['product_id'];
                } elseif ($buyRequest->getData('super_attribute')) {
                    $productId = $buyRequest->getData('product');
                } else {
                    $productId = $this->getParentId($products->getId());
                }

                $products = $this->productRepository->getById($productId);
            }
        } catch (Exception $e) {
            $products = new DataObject([]);
        }

        return $products;
    }
}
