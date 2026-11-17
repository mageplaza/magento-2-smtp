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
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Helper;

use Magento\Bundle\Helper\Catalog\Product\Configuration as BundleConfiguration;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Product\Configuration as CatalogConfiguration;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Customer\Model\Attribute;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\GroupFactory;
use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Customer\Block\Address\Renderer\RendererInterface;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\Region;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\UrlInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Reports\Model\ResourceModel\Order\Collection as ReportOrderCollection;
use Magento\Reports\Model\ResourceModel\Order\CollectionFactory as ReportOrderCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\ResourceModel\AbandonedCart\Grid\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

#[CoversClass(EmailMarketing::class)]
class EmailMarketingTest extends TestCase
{
    use MockCreationTrait;

    private EmailMarketing $helper;

    protected function setUp(): void
    {
        // Skip the constructor and inject only what each test needs.
        $this->helper = $this->getMockBuilder(EmailMarketing::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setResourceMocks($this->helper);

        // EmailMarketing (via Mageplaza\Core\Helper\AbstractData) resolves jsonEncode()/
        // jsonDecode() through the global ObjectManager; stub it so the static calls
        // do not dereference a null instance.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonEncode')->willReturnCallback(static fn ($v) => json_encode($v));
        $jsonHelper->method('jsonDecode')->willReturnCallback(static fn ($v) => json_decode($v, true));
        $om = $this->createMock(ObjectManagerInterface::class);
        $om->method('get')->willReturn($jsonHelper);
        ObjectManager::setInstance($om);
    }

    private function setProperty(string $property, mixed $value, ?EmailMarketing $target = null): void
    {
        $ref = new ReflectionProperty(EmailMarketing::class, $property);
        $ref->setValue($target ?? $this->helper, $value);
    }

    private function setResourceMocks(EmailMarketing $helper): void
    {
        $this->setProperty('customerResource', $this->createMock(\Magento\Customer\Model\ResourceModel\Customer::class), $helper);
        $this->setProperty('groupResource', $this->createMock(\Magento\Customer\Model\ResourceModel\Group::class), $helper);
        $this->setProperty('regionResource', $this->createMock(\Magento\Directory\Model\ResourceModel\Region::class), $helper);
        $this->setProperty('quoteItemResource', $this->createMock(\Magento\Quote\Model\ResourceModel\Quote\Item::class), $helper);
        $this->setProperty('categoryResource', $this->createMock(\Magento\Catalog\Model\ResourceModel\Category::class), $helper);
    }

    private function partialHelper(array $onlyMethods): EmailMarketing&MockObject
    {
        $helper = $this->getMockBuilder(EmailMarketing::class)
            ->disableOriginalConstructor()
            ->onlyMethods($onlyMethods)
            ->getMock();
        $this->setResourceMocks($helper);

        return $helper;
    }


    public function testGetResourceQuoteReturnsInjectedInstance(): void
    {
        $resourceQuote = $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class);
        $this->setProperty('resourceQuote', $resourceQuote);

        $this->assertSame($resourceQuote, $this->helper->getResourceQuote());
    }

    public function testSplitSkuDelegates(): void
    {
        $catalogHelper = $this->createMock(CatalogHelper::class);
        $catalogHelper->expects($this->once())
            ->method('splitSku')
            ->with('ABC-123')
            ->willReturn(['ABC', '123']);
        $this->setProperty('catalogHelper', $catalogHelper);

        $this->assertSame(['ABC', '123'], $this->helper->splitSku('ABC-123'));
    }

    public function testFormatDateConvertsAndFormats(): void
    {
        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->expects($this->once())
            ->method('convertConfigTimeToUtc')
            ->with('2026-07-14 10:00:00')
            ->willReturn('2026-07-14 03:00:00');
        $localeDate->expects($this->once())
            ->method('formatDateTime')
            ->willReturn('Jul 14, 2026, 3:00:00 AM');
        $this->setProperty('_localeDate', $localeDate);

        $this->assertSame(
            'Jul 14, 2026, 3:00:00 AM',
            $this->helper->formatDate('2026-07-14 10:00:00')
        );
    }

    /**
     * @dataProvider emailMarketingConfigDelegateProvider
     */
    #[DataProvider('emailMarketingConfigDelegateProvider')]
    public function testConfigDelegatesToGetEmailMarketingConfig(string $method, string $code): void
    {
        $helper = $this->partialHelper(['getEmailMarketingConfig']);
        $helper->expects($this->once())
            ->method('getEmailMarketingConfig')
            ->with($code, 2)
            ->willReturn('configured-value');

        $this->assertSame('configured-value', $helper->{$method}(2));
    }

    public static function emailMarketingConfigDelegateProvider(): array
    {
        return [
            'getDefineVendor'     => ['getDefineVendor', 'define_vendor'],
            'getAppID'            => ['getAppID', 'app_id'],
            'getSubscriberConfig' => ['getSubscriberConfig', 'newsletter_subscriber'],
            'isTracking'          => ['isTracking', 'is_tracking'],
            'isPushNotification'  => ['isPushNotification', 'push_notification'],
        ];
    }

    public function testGetSecretKeyDecryptsConfigValue(): void
    {
        $helper = $this->partialHelper(['getEmailMarketingConfig']);
        $helper->method('getEmailMarketingConfig')->with('secret_key', 1)->willReturn('enc');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('enc')->willReturn('plain');
        $this->setProperty('encryptor', $encryptor, $helper);

        $this->assertSame('plain', $helper->getSecretKey(1));
    }

    public function testGetConnectTokenDecryptsConfigValue(): void
    {
        $helper = $this->partialHelper(['getEmailMarketingConfig']);
        $helper->method('getEmailMarketingConfig')->with('connectToken', 1)->willReturn('enc-token');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('enc-token')->willReturn('plain-token');
        $this->setProperty('encryptor', $encryptor, $helper);

        $this->assertSame('plain-token', $helper->getConnectToken(1));
    }


    public function testGetProductOptionsForBundle(): void
    {
        $bundleConfig  = $this->createMock(BundleConfiguration::class);
        $catalogConfig = $this->createMock(CatalogConfiguration::class);
        $this->setProperty('bundleProductConfiguration', $bundleConfig);
        $this->setProperty('productConfig', $catalogConfig);

        // A full mock avoids executing AbstractItem::getProduct(), which fatals
        // ("setFinalPrice() on null") when constructed via a real Item instance.
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn(Type::TYPE_BUNDLE);

        $bundleConfig->expects($this->once())->method('getOptions')->with($item)->willReturn(['b']);
        $catalogConfig->expects($this->never())->method('getOptions');

        $this->assertSame(['b'], $this->helper->getProductOptions($item));
    }

    public function testGetProductOptionsForSimple(): void
    {
        $bundleConfig  = $this->createMock(BundleConfiguration::class);
        $catalogConfig = $this->createMock(CatalogConfiguration::class);
        $this->setProperty('bundleProductConfiguration', $bundleConfig);
        $this->setProperty('productConfig', $catalogConfig);

        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('simple');

        $catalogConfig->expects($this->once())->method('getOptions')->with($item)->willReturn(['s']);
        $bundleConfig->expects($this->never())->method('getOptions');

        $this->assertSame(['s'], $this->helper->getProductOptions($item));
    }


    public function testGetRecoveryUrlBuildsTokenAndUrl(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('isUrlSecure')->willReturn(true);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);
        $this->setProperty('storeManager', $storeManager);

        $frontendUrl = $this->createMock(UrlInterface::class);
        $frontendUrl->expects($this->once())->method('setScope')->with(1);
        $frontendUrl->expects($this->once())
            ->method('getUrl')
            ->with('mpsmtp/abandonedcart/recover', [
                '_current' => false,
                '_nosid'   => true,
                'token'    => 'tok_' . base64_encode('5'),
                '_secure'  => true,
            ])
            ->willReturn('https://x.test/mpsmtp/abandonedcart/recover');
        $this->setProperty('frontendUrl', $frontendUrl);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getStoreId', 'getId', 'getMpSmtpAceToken']);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getId')->willReturn(5);
        $quote->method('getMpSmtpAceToken')->willReturn('tok');

        $this->assertSame('https://x.test/mpsmtp/abandonedcart/recover', $this->helper->getRecoveryUrl($quote));
    }


    public function testGetCustomerNameFromQuoteNames(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        $quote = $this->createPartialMockWithReflection(
            Quote::class,
            ['getCustomerFirstname', 'getCustomerLastname', 'getCustomerId', 'getCustomerEmail']
        );
        $quote->method('getCustomerFirstname')->willReturn('John');
        $quote->method('getCustomerLastname')->willReturn('Doe');
        $quote->method('getCustomerId')->willReturn(0);
        $quote->method('getCustomerEmail')->willReturn('');

        $this->assertSame('John Doe', $this->helper->getCustomerName($quote));
    }

    public function testGetCustomerNameFromRegisteredCustomer(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        $customer = $this->createPartialMockWithReflection(Customer::class, ['getId', 'getFirstname', 'getLastname']);
        $customer->method('getId')->willReturn(5);
        $customer->method('getFirstname')->willReturn('Jane');
        $customer->method('getLastname')->willReturn('Roe');

        $quote = $this->createPartialMockWithReflection(
            Quote::class,
            ['getCustomerFirstname', 'getCustomerLastname', 'getCustomerId', 'getCustomer']
        );
        $quote->method('getCustomerFirstname')->willReturn('');
        $quote->method('getCustomerLastname')->willReturn('');
        $quote->method('getCustomerId')->willReturn(5);
        $quote->method('getCustomer')->willReturn($customer);

        $this->assertSame('Jane Roe', $this->helper->getCustomerName($quote));
    }

    public function testGetCustomerNameFallsBackToEmail(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        $quote = $this->createPartialMockWithReflection(
            Quote::class,
            ['getCustomerFirstname', 'getCustomerLastname', 'getCustomerId', 'getCustomerEmail']
        );
        $quote->method('getCustomerFirstname')->willReturn('');
        $quote->method('getCustomerLastname')->willReturn('');
        $quote->method('getCustomerId')->willReturn(0);
        $quote->method('getCustomerEmail')->willReturn('guest.buyer@example.com');

        $this->assertSame('guest.buyer', $this->helper->getCustomerName($quote));
    }

    public function testGetCustomerNameReturnsEmptyWhenNothingAvailable(): void
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $this->setProperty('escaper', $escaper);

        $quote = $this->createPartialMockWithReflection(
            Quote::class,
            ['getCustomerFirstname', 'getCustomerLastname', 'getCustomerId', 'getCustomerEmail']
        );
        $quote->method('getCustomerFirstname')->willReturn('');
        $quote->method('getCustomerLastname')->willReturn('');
        $quote->method('getCustomerId')->willReturn(0);
        $quote->method('getCustomerEmail')->willReturn('');

        $this->assertSame('', $this->helper->getCustomerName($quote));
    }


    private function createOrderDataHelper(): EmailMarketing&MockObject
    {
        $helper = $this->partialHelper([
            'getTags', 'getDataAddress', 'getOrderViewUrl',
            'getShipmentOrCreditmemoItems', 'getCartItems', 'formatDate',
        ]);
        $helper->method('formatDate')->willReturnArgument(0);
        $helper->method('getTags')->willReturn('Main Website Store,base,General');
        $helper->method('getOrderViewUrl')->willReturn('https://x.test/order/view');
        $helper->method('getCartItems')->willReturn([]);
        $helper->method('getShipmentOrCreditmemoItems')->willReturn([]);
        $helper->method('getDataAddress')->willReturn(['first_name' => 'John']);

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $this->setProperty('_localeDate', $localeDate, $helper);

        $customerFactory = $this->createPartialMock(CustomerFactory::class, ['create']);
        $customer        = $this->createMock(Customer::class);
        $customerFactory->method('create')->willReturn($customer);
        $this->setProperty('customerFactory', $customerFactory, $helper);

        return $helper;
    }

    public function testGetOrderDataForPlainOrderSetsCustomerAndOrderFields(): void
    {
        $helper = $this->createOrderDataHelper();

        $billingAddress = $this->createMock(\Magento\Sales\Model\Order\Address::class);
        $billingAddress->method('getTelephone')->willReturn('0123456789');

        $methodInstance = $this->createMock(MethodInterface::class);
        $methodInstance->method('getTitle')->willReturn('Check / Money order');
        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(10);
        $order->method('getIncrementId')->willReturn('000000010');
        $order->method('getShippingAmount')->willReturn(5.0);
        $order->method('getBaseCurrencyCode')->willReturn('USD');
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $order->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('john@example.com');
        $order->method('getCustomerId')->willReturn(9);
        $order->method('getCustomerFirstname')->willReturn('John');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStatus')->willReturn('processing');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getBaseGrandTotal')->willReturn(105.0);
        $order->method('getBaseSubtotal')->willReturn(100.0);
        $order->method('getBaseTaxAmount')->willReturn(0.0);
        $order->method('getBaseShippingAmount')->willReturn(5.0);
        $order->method('getBaseDiscountAmount')->willReturn(0.0);

        $result = $helper->getOrderData($order);

        $this->assertSame(10, $result['id']);
        $this->assertSame('#000000010', $result['name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame(9, $result['customer']['id']);
        $this->assertSame('John', $result['customer']['first_name']);
        $this->assertSame('0123456789', $result['customer']['telephone']);
        $this->assertSame('https://x.test/order/view', $result['order_status_url']);
        $this->assertArrayHasKey('billing_address', $result);
        $this->assertArrayNotHasKey('shipping_address', $result);
        $this->assertSame('Check / Money order', $result['gateway']);
        $this->assertSame('processing', $result['status']);
        $this->assertSame(105.0, $result['total_price']);
        $this->assertSame(100.0, $result['subtotal_price']);
    }

    public function testGetOrderDataForInvoiceUsesParentOrderCustomerData(): void
    {
        $helper = $this->createOrderDataHelper();

        $order = $this->createMock(Order::class);
        $order->method('getCustomerEmail')->willReturn('parent@example.com');
        $order->method('getCustomerId')->willReturn(3);
        $order->method('getCustomerFirstname')->willReturn('Parent');
        $order->method('getCustomerLastname')->willReturn('Buyer');
        $order->method('getShippingAmount')->willReturn(7.0);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn(20);
        $invoice->method('getIncrementId')->willReturn('000000020');
        $invoice->method('getShippingAmount')->willReturn(7.0);
        $invoice->method('getBaseCurrencyCode')->willReturn('USD');
        $invoice->method('getOrderCurrencyCode')->willReturn('USD');
        $invoice->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $invoice->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');
        $invoice->method('getStoreId')->willReturn(1);
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getOrderId')->willReturn(10);
        $invoice->method('getBillingAddress')->willReturn(null);
        $invoice->method('getShippingAddress')->willReturn(null);

        $result = $helper->getOrderData($invoice);

        $this->assertSame('parent@example.com', $result['email']);
        $this->assertSame(3, $result['customer']['id']);
        $this->assertSame('Parent', $result['customer']['first_name']);
        $this->assertSame(10, $result['order_id']);
        $this->assertArrayNotHasKey('order_status_url', $result);
    }

    public function testGetOrderDataForShipmentWithTracksPopulatesTrackingData(): void
    {
        $helper = $this->createOrderDataHelper();

        $order = $this->createMock(Order::class);
        $order->method('getCustomerEmail')->willReturn('parent@example.com');
        $order->method('getShippingAmount')->willReturn(7.0);
        $shippingAddress = $this->createMock(\Magento\Sales\Model\Order\Address::class);
        $shippingAddress->method('getFirstname')->willReturn('John');
        $shippingAddress->method('getLastname')->willReturn('Doe');
        $shippingAddress->method('getStreetLine')->willReturn('123 Main St');
        $shippingAddress->method('getCity')->willReturn('City');
        $shippingAddress->method('getPostcode')->willReturn('10000');
        $shippingAddress->method('getCountryId')->willReturn('US');
        $shippingAddress->method('getTelephone')->willReturn('0123456789');
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $track = $this->createPartialMockWithReflection(
            \Magento\Sales\Model\Order\Shipment\Track::class,
            ['getTitle', 'getTrackNumber']
        );
        $track->method('getTitle')->willReturn('UPS');
        $track->method('getTrackNumber')->willReturn('1Z999');

        $shippingHelper = $this->createMock(ShippingHelper::class);
        $shippingHelper->method('getTrackingPopupUrlBySalesModel')->willReturn('https://x.test/track');
        $this->setProperty('shippingHelper', $shippingHelper, $helper);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getId')->willReturn(30);
        $shipment->method('getIncrementId')->willReturn('000000030');
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getOrderId')->willReturn(10);
        $shipment->method('getBillingAddress')->willReturn(null);
        $shipment->method('getShippingAddress')->willReturn(null);
        // getData is also the magic __call() fallthrough for undeclared accessors
        // (e.g. getShippingAmount()) on this class — a bare with('tracks') stub
        // would leave those other calls unmatched, so cover every key safely.
        $shipment->method('getData')->willReturnCallback(
            static fn (string $key) => $key === 'tracks' ? [$track] : null
        );

        $result = $helper->getOrderData($shipment);

        $this->assertSame('https://x.test/track', $result['trackingUrl']);
        $this->assertSame([['company' => 'UPS', 'number' => '1Z999', 'url' => 'https://x.test/track']], $result['tracks']);
        $this->assertSame('John', $result['destination']['first_name']);
    }

    public function testGetOrderDataForCreditmemoUsesCreditmemoPath(): void
    {
        $helper = $this->partialHelper([
            'getTags', 'getDataAddress', 'getOrderViewUrl',
            'getShipmentOrCreditmemoItems', 'getCartItems', 'formatDate',
        ]);
        $helper->method('formatDate')->willReturnArgument(0);
        $helper->method('getTags')->willReturn('tags');
        $helper->method('getCartItems')->willReturn([]);
        $helper->method('getShipmentOrCreditmemoItems')->willReturn([]);
        $helper->method('getDataAddress')->willReturn([]);
        $helper->expects($this->once())
            ->method('getOrderViewUrl')
            ->with($this->anything(), $this->anything(), 'sales/order/creditmemo')
            ->willReturn('https://x.test/creditmemo');

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $this->setProperty('_localeDate', $localeDate, $helper);

        $customerFactory = $this->createPartialMock(CustomerFactory::class, ['create']);
        $customer        = $this->createMock(Customer::class);
        $customerFactory->method('create')->willReturn($customer);
        $this->setProperty('customerFactory', $customerFactory, $helper);

        $order = $this->createMock(Order::class);
        $order->method('getCustomerEmail')->willReturn('parent@example.com');
        $order->method('getShippingAmount')->willReturn(7.0);
        $order->method('getShippingAddress')->willReturn(null);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getOrderId')->willReturn(10);
        $creditmemo->method('getBillingAddress')->willReturn(null);
        $creditmemo->method('getShippingAddress')->willReturn(null);

        $result = $helper->getOrderData($creditmemo);

        $this->assertSame('https://x.test/creditmemo', $result['order_status_url']);
    }


    public function testGetDataAddressMapsAllFields(): void
    {
        $address = $this->createPartialMockWithReflection(Address::class, [
            'getFirstname', 'getLastname', 'getStreetLine', 'getCity', 'getPostcode',
            'getCountryId', 'getTelephone', 'getRegion', 'getCompany',
        ]);
        $address->method('getFirstname')->willReturn('John');
        $address->method('getLastname')->willReturn('Doe');
        $address->method('getStreetLine')->willReturnMap([[1, '123 Main St'], [2, 'Apt 4'], [3, '']]);
        $address->method('getCity')->willReturn('City');
        $address->method('getPostcode')->willReturn('10000');
        $address->method('getCountryId')->willReturn('US');
        $address->method('getTelephone')->willReturn('0123456789');
        $address->method('getRegion')->willReturn('NY');
        $address->method('getCompany')->willReturn('ACME');

        $result = $this->helper->getDataAddress($address);

        $this->assertSame('John', $result['first_name']);
        $this->assertSame('Doe', $result['last_name']);
        $this->assertSame('123 Main St', $result['address1']);
        $this->assertSame('Apt 4 ', $result['address2']);
        $this->assertSame('US', $result['country']);
        $this->assertSame('NY', $result['province']);
        $this->assertSame('ACME', $result['company']);
        $this->assertSame('JohnDoe', $result['name']);
    }


    public function testSendOrderRequestSetsCheckoutIdWhenNoUrlGiven(): void
    {
        $helper = $this->partialHelper(['getOrderData', 'sendRequest']);
        $helper->method('getOrderData')->willReturn(['id' => 1]);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(static fn (array $data) => ($data['checkout_id'] ?? null) === 7), EmailMarketing::ORDER_URL);

        $order = $this->createMock(Order::class);
        $order->method('getQuoteId')->willReturn(7);
        $order->method('getStoreId')->willReturn(1);

        $helper->sendOrderRequest($order);
    }

    public function testSendOrderRequestUsesGivenUrlWithoutCheckoutId(): void
    {
        $helper = $this->partialHelper(['getOrderData', 'sendRequest']);
        $helper->method('getOrderData')->willReturn(['id' => 1]);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(static fn (array $data) => !array_key_exists('checkout_id', $data)), EmailMarketing::CREDITMEMO_URL);

        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);

        $helper->sendOrderRequest($order, EmailMarketing::CREDITMEMO_URL);
    }


    public function testUpdateOrderStatusRequestSetsUpdateFlagAndSends(): void
    {
        $helper = $this->partialHelper(['sendRequest']);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with(['id' => 1], EmailMarketing::ORDER_URL)
            ->willReturn(['success' => true]);

        $this->assertSame(['success' => true], $helper->updateOrderStatusRequest(['id' => 1]));

        $ref = new ReflectionProperty(EmailMarketing::class, 'isPUT');
        $this->assertTrue($ref->getValue($helper));
    }


    public function testGetOrderViewUrlUsesGivenPath(): void
    {
        $frontendUrl = $this->createMock(UrlInterface::class);
        $frontendUrl->expects($this->once())->method('setScope')->with(1);
        $frontendUrl->expects($this->once())
            ->method('getUrl')
            ->with('sales/order/shipment', ['order_id' => 5])
            ->willReturn('https://x.test/shipment');
        $this->setProperty('frontendUrl', $frontendUrl);

        $this->assertSame('https://x.test/shipment', $this->helper->getOrderViewUrl(1, 5, 'sales/order/shipment'));
    }

    public function testGetOrderViewUrlDefaultsToOrderView(): void
    {
        $frontendUrl = $this->createMock(UrlInterface::class);
        $frontendUrl->expects($this->once())
            ->method('getUrl')
            ->with('sales/order/view', ['order_id' => 5])
            ->willReturn('https://x.test/order');
        $this->setProperty('frontendUrl', $frontendUrl);

        $this->assertSame('https://x.test/order', $this->helper->getOrderViewUrl(1, 5));
    }


    private function createAceDataHelper(): EmailMarketing&MockObject
    {
        $helper = $this->partialHelper([
            'getQuoteUpdatedAt', 'formatDate', 'getCustomerName', 'getCartItems',
            'getRecoveryUrl', 'getShippingAddress', 'getBillingAddress',
        ]);
        $helper->method('formatDate')->willReturnArgument(0);
        $helper->method('getCustomerName')->willReturn('John Doe');
        $helper->method('getCartItems')->willReturn([]);
        $helper->method('getRecoveryUrl')->willReturn('https://x.test/recover');
        $helper->method('getShippingAddress')->willReturn([]);
        $helper->method('getBillingAddress')->willReturn([]);

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $this->setProperty('_localeDate', $localeDate, $helper);

        return $helper;
    }

    private function createAceQuote(array $methods): Quote&MockObject
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, array_unique(array_merge($methods, [
            'getId', 'getIsActive', 'getCreatedAt', 'getCustomerEmail', 'getCustomerId',
            'getCustomerFirstname', 'getCustomerLastname', 'getStoreCurrencyCode',
            'getBaseSubtotal', 'getData', 'isVirtual', 'getShippingAddress', 'getStoreId',
        ])));
        // Real Quote::getStoreId() (vendor/magento/module-quote/Model/Quote.php:756-761)
        // falls back to $this->_storeManager->getStore() when store_id is unset — that
        // dependency is never constructed on a disableOriginalConstructor() mock.
        $quote->method('getStoreId')->willReturn(1);

        return $quote;
    }

    public function testGetACEDataForActiveQuoteHasNullCompletedAt(): void
    {
        $helper = $this->createAceDataHelper();
        $helper->method('getQuoteUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $quote = $this->createAceQuote([]);
        $quote->method('getIsActive')->willReturn(1);
        $quote->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $quote->method('isVirtual')->willReturn(true);

        $result = $helper->getACEData($quote);

        $this->assertNull($result['completed_at']);
    }

    public function testGetACEDataForInactiveQuoteSetsCompletedAt(): void
    {
        $helper = $this->createAceDataHelper();
        $helper->method('getQuoteUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $quote = $this->createAceQuote([]);
        $quote->method('getIsActive')->willReturn(0);
        $quote->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $quote->method('isVirtual')->willReturn(true);

        $result = $helper->getACEData($quote);

        $this->assertSame('2026-01-02 00:00:00', $result['completed_at']);
    }

    public function testGetACEDataFallsBackToUpdatedAtWhenNoCreatedAt(): void
    {
        $helper = $this->createAceDataHelper();
        $helper->method('getQuoteUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $quote = $this->createAceQuote([]);
        $quote->method('getIsActive')->willReturn(1);
        $quote->method('getCreatedAt')->willReturn('');
        $quote->method('isVirtual')->willReturn(true);

        $result = $helper->getACEData($quote);

        $this->assertSame('2026-01-02 00:00:00', $result['created_at']);
    }

    public function testGetACEDataSetsShippingTaxForNonVirtualQuote(): void
    {
        $helper = $this->createAceDataHelper();
        $helper->method('getQuoteUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $shippingAddress = $this->createPartialMockWithReflection(Address::class, ['getBaseTaxAmount']);
        $shippingAddress->method('getBaseTaxAmount')->willReturn(9.5);

        $quote = $this->createAceQuote([]);
        $quote->method('getIsActive')->willReturn(1);
        $quote->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);

        $result = $helper->getACEData($quote);

        $this->assertSame(9.5, $result['total_tax']);
    }

    public function testGetACEDataSetsZeroTaxForVirtualQuote(): void
    {
        $helper = $this->createAceDataHelper();
        $helper->method('getQuoteUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $quote = $this->createAceQuote([]);
        $quote->method('getIsActive')->willReturn(1);
        $quote->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $quote->method('isVirtual')->willReturn(true);

        $result = $helper->getACEData($quote);

        $this->assertSame(0, $result['total_tax']);
    }


    public function testGetShippingAddressDelegatesToGetAddress(): void
    {
        $shippingAddress = $this->createPartialMockWithReflection(Address::class, []);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getShippingAddress', 'isVirtual']);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('isVirtual')->willReturn(true);

        $this->assertSame([], $this->helper->getShippingAddress($quote));
    }

    public function testGetBillingAddressReturnsEmptyArrayWhenNoPaymentMethod(): void
    {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn('');

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getPayment']);
        $quote->method('getPayment')->willReturn($payment);

        $this->assertSame([], $this->helper->getBillingAddress($quote));
    }

    public function testGetBillingAddressAppendsMethodSuffixWhenNotOsc(): void
    {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $billingAddress = $this->createPartialMockWithReflection(Address::class, ['getName']);
        $billingAddress->method('getName')->willReturn('field-marker');

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getPayment', 'getBillingAddress', 'isVirtual']);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('isVirtual')->willReturn(false);

        $result = $this->helper->getBillingAddress($quote, ['billingAddresscheckmo.firstname' => 'John', 'billingAddresscheckmo.lastname' => 'Doe']);

        $this->assertSame('John Doe', $result['name']);
    }

    public function testGetBillingAddressOmitsMethodSuffixWhenOsc(): void
    {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $billingAddress = $this->createPartialMockWithReflection(Address::class, ['getName']);
        $billingAddress->method('getName')->willReturn('field-marker');

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getPayment', 'getBillingAddress', 'isVirtual']);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('isVirtual')->willReturn(false);

        $result = $this->helper->getBillingAddress($quote, ['billingAddress.firstname' => 'John', 'billingAddress.lastname' => 'Doe'], true);

        $this->assertSame('John Doe', $result['name']);
    }


    public function testGetAddressReturnsEmptyArrayForVirtualQuote(): void
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, ['isVirtual']);
        $quote->method('isVirtual')->willReturn(true);

        $address = $this->createPartialMockWithReflection(Address::class, []);

        $this->assertSame([], $this->helper->getAddress($quote, $address, null, 'shippingAddress'));
    }

    public function testGetAddressUsesAddressObjectGettersWithoutOverride(): void
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, ['isVirtual']);
        $quote->method('isVirtual')->willReturn(false);

        $address = $this->createPartialMockWithReflection(Address::class, [
            'getName', 'getLastname', 'getTelephone', 'getCompany', 'getCountryId',
            'getPostcode', 'getStreetLine', 'getCity', 'getRegionCode', 'getRegion',
        ]);
        $address->method('getName')->willReturn('John Doe');
        $address->method('getLastname')->willReturn('Doe');
        $address->method('getTelephone')->willReturn('0123456789');
        $address->method('getCompany')->willReturn('ACME');
        $address->method('getCountryId')->willReturn('US');
        $address->method('getPostcode')->willReturn('10000');
        $address->method('getStreetLine')->willReturnMap([[1, '123 Main St'], [2, '']]);
        $address->method('getCity')->willReturn('City');
        $address->method('getRegionCode')->willReturn('NY');
        $address->method('getRegion')->willReturn('New York');

        $result = $this->helper->getAddress($quote, $address, null, 'shippingAddress');

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('US', $result['country_code']);
        $this->assertSame('NY', $result['province_code']);
        $this->assertSame('New York', $result['province']);
    }

    public function testGetAddressUsesOverrideArrayValues(): void
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, ['isVirtual']);
        $quote->method('isVirtual')->willReturn(false);

        $address = $this->createPartialMockWithReflection(Address::class, [
            'getName', 'getLastname', 'getTelephone', 'getCompany', 'getCountryId',
            'getPostcode', 'getStreetLine', 'getCity', 'getRegionCode', 'getRegion',
        ]);
        $address->method('getName')->willReturn('Fallback Name');

        $addr = [
            'shippingAddress.firstname' => 'Over',
            'shippingAddress.lastname'  => 'Ride',
            'shippingAddress.city'      => 'Override City',
        ];

        $result = $this->helper->getAddress($quote, $address, $addr, 'shippingAddress');

        $this->assertSame('Over Ride', $result['name']);
        $this->assertSame('Override City', $result['city']);
    }

    public function testGetAddressUsesRegionLoadWhenRegionIdOverridden(): void
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, ['isVirtual']);
        $quote->method('isVirtual')->willReturn(false);

        $address = $this->createPartialMockWithReflection(Address::class, [
            'getName', 'getLastname', 'getTelephone', 'getCompany', 'getCountryId',
            'getPostcode', 'getStreetLine', 'getCity', 'getRegionCode', 'getRegion',
        ]);
        $address->method('getName')->willReturn('John');

        $region = $this->createPartialMockWithReflection(Region::class, ['load', 'getCode', 'getName']);
        $region->method('getCode')->willReturn('CA');
        $region->method('getName')->willReturn('California');
        $this->setProperty('region', $region);
        $resource = $this->createMock(\Magento\Directory\Model\ResourceModel\Region::class);
        $resource->expects($this->exactly(2))->method('load')->with($region, 15);
        $this->setProperty('regionResource', $resource);

        $addr = ['shippingAddress.region_id' => 15];

        $result = $this->helper->getAddress($quote, $address, $addr, 'shippingAddress');

        $this->assertSame('CA', $result['province_code']);
        $this->assertSame('California', $result['province']);
    }


    private function createShipmentItemsHelper(): EmailMarketing&MockObject
    {
        $helper = $this->partialHelper([
            'getProductFromItem', 'getProductImage', 'formatDate', 'getCategories', 'getProductBySku',
        ]);
        $helper->method('formatDate')->willReturnArgument(0);
        $helper->method('getCategories')->willReturn([]);
        $helper->method('getProductImage')->willReturn('https://x.test/img.jpg');

        $product = $this->createPartialMockWithReflection(Product::class, ['getSku', 'getProductUrl', 'getCategoryIds']);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getProductUrl')->willReturn('https://x.test/product');
        $product->method('getCategoryIds')->willReturn([1]);
        $helper->method('getProductFromItem')->willReturn($product);

        $skuProduct = $this->createPartialMockWithReflection(Product::class, ['getProductUrl', 'getCategoryIds']);
        $skuProduct->method('getProductUrl')->willReturn('https://x.test/sku-product');
        $skuProduct->method('getCategoryIds')->willReturn([1]);
        $helper->method('getProductBySku')->willReturn($skuProduct);

        return $helper;
    }

    private function createOrderItem(array $methods): OrderItem&MockObject
    {
        return $this->createPartialMockWithReflection(OrderItem::class, array_unique(array_merge($methods, [
            'getId', 'getParentItemId', 'getProductId', 'getSku', 'getData',
        ])));
    }

    public function testGetShipmentOrCreditmemoItemsBuildsSimpleItemRow(): void
    {
        $helper = $this->createShipmentItemsHelper();

        $orderItem = $this->createOrderItem([]);
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getProductId')->willReturn(100);
        $orderItem->method('getSku')->willReturn('SKU-1');
        $orderItem->method('getData')->with('product_type')->willReturn('simple');

        $creditmemoItem = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt', 'getData',
        ]);
        $creditmemoItem->method('getOrderItem')->willReturn($orderItem);
        $creditmemoItem->method('getName')->willReturn('Product One');
        $creditmemoItem->method('getQty')->willReturn(1.0);
        $creditmemoItem->method('getBasePrice')->willReturn(20.0);
        $creditmemoItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $creditmemoItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');
        // getSku()/getProductId() are real declared methods (vendor/magento/module-sales/
        // Model/Order/Creditmemo/Item.php:509,549) that both delegate to $this->getData(...);
        // a single ->with('sku') stub throws when the SUT's getProductId() call hits
        // getData('product_id') on the same mocked method — map every key actually used.
        $creditmemoItem->method('getData')->willReturnMap([
            ['sku', null, 'SKU-1'],
            ['product_id', null, 100],
        ]);

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$creditmemoItem]);
        $creditmemo->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $creditmemo->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $result = $helper->getShipmentOrCreditmemoItems($creditmemo);

        $this->assertCount(1, $result);
        $this->assertSame('simple', $result[0]['type']);
        $this->assertSame('Product One', $result[0]['name']);
        $this->assertArrayNotHasKey('bundle_items', $result[0]);
    }

    public function testGetShipmentOrCreditmemoItemsAppendsBundleChildToParent(): void
    {
        $helper = $this->createShipmentItemsHelper();

        $parentOrderItem = $this->createOrderItem([]);
        $parentOrderItem->method('getId')->willReturn(1);
        $parentOrderItem->method('getParentItemId')->willReturn(null);
        $parentOrderItem->method('getProductId')->willReturn(100);
        $parentOrderItem->method('getSku')->willReturn('BUNDLE-1');
        // getData($key, $index = null): the mock receives the padded 2-arg signature,
        // so the row must include the defaulted null index (rules/unit-testing.md).
        $parentOrderItem->method('getData')->willReturnMap([
            ['product_type', null, Type::TYPE_BUNDLE],
        ]);

        $parentItem = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt', 'getData',
        ]);
        $parentItem->method('getOrderItem')->willReturn($parentOrderItem);
        $parentItem->method('getName')->willReturn('Bundle');
        $parentItem->method('getQty')->willReturn(1.0);
        $parentItem->method('getBasePrice')->willReturn(50.0);
        $parentItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $parentItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $childOrderItem = $this->createOrderItem([]);
        $childOrderItem->method('getId')->willReturn(2);
        $childOrderItem->method('getParentItemId')->willReturn(1);
        $childOrderItem->method('getProductId')->willReturn(101);
        $childOrderItem->method('getSku')->willReturn('CHILD-1');

        $childItem = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt',
        ]);
        $childItem->method('getOrderItem')->willReturn($childOrderItem);
        $childItem->method('getName')->willReturn('Bundle Child');
        $childItem->method('getQty')->willReturn(1.0);
        $childItem->method('getBasePrice')->willReturn(10.0);
        $childItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $childItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$parentItem, $childItem]);
        $creditmemo->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $creditmemo->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $result = $helper->getShipmentOrCreditmemoItems($creditmemo);

        $this->assertCount(1, $result);
        $this->assertSame('Bundle', $result[0]['name']);
        $this->assertCount(1, $result[0]['bundle_items']);
        $this->assertSame('Bundle Child', $result[0]['bundle_items'][0]['name']);
    }

    public function testGetShipmentOrCreditmemoItemsSetsVariantFieldsOnParent(): void
    {
        $helper = $this->createShipmentItemsHelper();

        $parentOrderItem = $this->createOrderItem([]);
        $parentOrderItem->method('getId')->willReturn(1);
        $parentOrderItem->method('getParentItemId')->willReturn(null);
        $parentOrderItem->method('getProductId')->willReturn(100);
        $parentOrderItem->method('getSku')->willReturn('CONF-1');
        $parentOrderItem->method('getData')->willReturnMap([
            ['product_type', Configurable::TYPE_CODE],
        ]);

        $parentItem = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt',
        ]);
        $parentItem->method('getOrderItem')->willReturn($parentOrderItem);
        $parentItem->method('getName')->willReturn('Configurable');
        $parentItem->method('getQty')->willReturn(1.0);
        $parentItem->method('getBasePrice')->willReturn(50.0);
        $parentItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $parentItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $childOrderItem = $this->createOrderItem([]);
        $childOrderItem->method('getId')->willReturn(2);
        $childOrderItem->method('getParentItemId')->willReturn(1);
        $childOrderItem->method('getProductId')->willReturn(101);
        $childOrderItem->method('getSku')->willReturn('CONF-1-SIMPLE');

        $childItem = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt',
        ]);
        $childItem->method('getOrderItem')->willReturn($childOrderItem);
        $childItem->method('getName')->willReturn('Configurable - Red');
        $childItem->method('getQty')->willReturn(1.0);
        $childItem->method('getBasePrice')->willReturn(50.0);
        $childItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $childItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$parentItem, $childItem]);
        $creditmemo->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $creditmemo->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $result = $helper->getShipmentOrCreditmemoItems($creditmemo);

        $this->assertCount(1, $result);
        $this->assertSame('Configurable - Red', $result[0]['variant_title']);
        $this->assertSame(101, $result[0]['variant_id']);
    }

    public function testGetShipmentOrCreditmemoItemsInitializesBundleItemsForBundleParent(): void
    {
        $helper = $this->createShipmentItemsHelper();

        $orderItem = $this->createOrderItem([]);
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getProductId')->willReturn(100);
        $orderItem->method('getSku')->willReturn('BUNDLE-1');
        // getData($key, $index = null): the mock receives the padded 2-arg signature,
        // so the row must include the defaulted null index (rules/unit-testing.md).
        $orderItem->method('getData')->willReturnMap([
            ['product_type', null, Type::TYPE_BUNDLE],
        ]);

        $item = $this->createPartialMockWithReflection(CreditmemoItem::class, [
            'getOrderItem', 'getName', 'getQty', 'getBasePrice', 'getCreatedAt', 'getUpdatedAt',
        ]);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getName')->willReturn('Bundle');
        $item->method('getQty')->willReturn(1.0);
        $item->method('getBasePrice')->willReturn(50.0);
        $item->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $item->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$item]);
        $creditmemo->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $creditmemo->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');

        $result = $helper->getShipmentOrCreditmemoItems($creditmemo);

        $this->assertSame([], $result[0]['bundle_items']);
    }


    public function testGetOptionsWithNameFormatsOptions(): void
    {
        $helper = $this->partialHelper(['getProductOptions']);
        $item   = $this->createMock(Item::class);
        $item->method('getName')->willReturn('Item');
        $helper->method('getProductOptions')->with($item)->willReturn([['label' => 'Size', 'value' => 'M']]);

        $this->assertSame('Item Size:M', $helper->getOptionsWithName($item));
    }

    public function testFormatOptionsAppendsLabelValuePairs(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getName')->willReturn('ItemName');

        $this->assertSame(
            'ItemName Size:M',
            $this->helper->formatOptions($item, [['label' => 'Size', 'value' => 'M']])
        );
    }

    public function testFormatOptionsReturnsNameWhenNoOptions(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getName')->willReturn('ItemName');

        $this->assertSame('ItemName', $this->helper->formatOptions($item, []));
    }


    public function testGetItemOptionsFallsBackToFormatOptionsWhenNoProductOptions(): void
    {
        $orderItem = $this->createPartialMockWithReflection(OrderItem::class, ['getProductOptions', 'getName']);
        $orderItem->method('getProductOptions')->willReturn(null);
        $orderItem->method('getName')->willReturn('ItemName');

        $this->assertSame('ItemName', $this->helper->getItemOptions($orderItem));
    }

    public function testGetItemOptionsMergesAllOptionKeys(): void
    {
        $orderItem = $this->createPartialMockWithReflection(OrderItem::class, ['getProductOptions', 'getName']);
        $orderItem->method('getProductOptions')->willReturn([
            'options'             => [['label' => 'Size', 'value' => 'M']],
            'additional_options'  => [['label' => 'Gift', 'value' => 'Yes']],
            'attributes_info'     => [['label' => 'Color', 'value' => 'Red']],
        ]);
        $orderItem->method('getName')->willReturn('ItemName');

        $this->assertSame('ItemName Size:M Gift:Yes Color:Red', $this->helper->getItemOptions($orderItem));
    }


    public function testGetProductFromItemReturnsDirectProduct(): void
    {
        $product = $this->createMock(Product::class);

        $item = $this->createPartialMockWithReflection(Item::class, ['getProduct']);
        $item->method('getProduct')->willReturn($product);

        $this->assertSame($product, $this->helper->getProductFromItem($item));
    }

    public function testGetProductFromItemFallsBackToProductById(): void
    {
        $product = $this->createMock(Product::class);

        $item = $this->createPartialMockWithReflection(Item::class, ['getProduct', 'getProductId']);
        $item->method('getProduct')->willReturn(null);
        $item->method('getProductId')->willReturn(5);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('getById')->with(5)->willReturn($product);
        $this->setProperty('productRepository', $productRepository);

        $this->assertSame($product, $this->helper->getProductFromItem($item));
    }

    public function testGetProductFromItemReturnsEmptyDataObjectWhenBothFail(): void
    {
        $item = $this->createPartialMockWithReflection(Item::class, ['getProduct', 'getProductId']);
        $item->method('getProduct')->willReturn(null);
        $item->method('getProductId')->willReturn(5);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('getById')->willThrowException(new \Exception('not found'));
        $this->setProperty('productRepository', $productRepository);

        $result = $this->helper->getProductFromItem($item);

        $this->assertInstanceOf(DataObject::class, $result);
        $this->assertSame([], $result->getData());
    }


    public function testGetProductByIdReturnsProductWhenFound(): void
    {
        $product           = $this->createMock(Product::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())->method('getById')->with(9)->willReturn($product);
        $this->setProperty('productRepository', $productRepository);

        $this->assertSame($product, $this->helper->getProductById(9));
    }

    public function testGetProductByIdReturnsNullOnException(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('getById')->willThrowException(new \Exception('boom'));
        $this->setProperty('productRepository', $productRepository);

        $this->assertNull($this->helper->getProductById(9));
    }


    private function createCartItemsHelper(): EmailMarketing&MockObject
    {
        $helper = $this->partialHelper([
            'getProductFromItem', 'getProductImage', 'formatDate', 'getCategories',
            'getProductBySku', 'getOptionsWithName', 'getItemOptions', 'getDefineVendor',
        ]);
        $helper->method('formatDate')->willReturnArgument(0);
        $helper->method('getCategories')->willReturn([]);
        $helper->method('getProductImage')->willReturn('https://x.test/img.jpg');
        $helper->method('getDefineVendor')->willReturn('vendor_attribute');

        $product = $this->createPartialMockWithReflection(Product::class, ['getSku', 'getProductUrl', 'getCategoryIds']);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getProductUrl')->willReturn('https://x.test/product');
        $product->method('getCategoryIds')->willReturn([1]);
        $helper->method('getProductFromItem')->willReturn($product);

        return $helper;
    }

    private function createQuoteCartItem(array $data): Item&MockObject
    {
        $item = $this->createPartialMockWithReflection(Item::class, [
            'getParentItemId', 'getCreatedAt', 'getUpdatedAt', 'getName', 'getBasePrice',
            'getBaseTaxAmount', 'getQty', 'getSku', 'getProductId', 'getBaseRowTotal',
            'getHasChildren', 'getChildren',
        ]);
        $item->method('getParentItemId')->willReturn($data['parentItemId'] ?? null);
        $item->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $item->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');
        $item->method('getName')->willReturn($data['name'] ?? 'Item');
        $item->method('getBasePrice')->willReturn($data['basePrice'] ?? 10.0);
        $item->method('getBaseTaxAmount')->willReturn($data['baseTaxAmount'] ?? 1.0);
        $item->method('getQty')->willReturn($data['qty'] ?? 1.0);
        $item->method('getSku')->willReturn($data['sku'] ?? 'SKU-1');
        $item->method('getProductId')->willReturn($data['productId'] ?? 100);
        $item->method('getBaseRowTotal')->willReturn($data['baseRowTotal'] ?? 10.0);
        $item->method('getHasChildren')->willReturn($data['hasChildren'] ?? false);
        $item->method('getChildren')->willReturn($data['children'] ?? []);
        $item->setData('product_type', $data['productType'] ?? 'simple');
        $item->setData('sku', $data['sku'] ?? 'SKU-1');

        return $item;
    }

    public function testGetCartItemsForQuoteSetsLinePriceAndVariantFields(): void
    {
        $helper = $this->createCartItemsHelper();
        $helper->method('getOptionsWithName')->willReturn('Configurable Size:M');

        $skuProduct = $this->createPartialMockWithReflection(
            Product::class,
            ['getProductUrl', 'getCategoryIds', 'getCustomAttribute']
        );
        $skuProduct->method('getProductUrl')->willReturn('https://x.test/sku');
        $skuProduct->method('getCategoryIds')->willReturn([1]);
        $skuProduct->method('getCustomAttribute')->willReturn(null);
        $helper->method('getProductBySku')->willReturn($skuProduct);

        $child = $this->createQuoteCartItem(['name' => 'Configurable - Red', 'productId' => 101, 'basePrice' => 20.0]);
        $item  = $this->createQuoteCartItem([
            'productType' => Configurable::TYPE_CODE,
            'hasChildren' => true,
            'children'    => [$child],
            'baseRowTotal' => 20.0,
        ]);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getAllItems']);
        $quote->method('getAllItems')->willReturn([$item]);

        $result = $helper->getCartItems($quote);

        $this->assertCount(1, $result);
        $this->assertSame(20.0, $result[0]['line_price']);
        $this->assertSame('Configurable Size:M', $result[0]['name']);
        $this->assertSame('Configurable - Red', $result[0]['variant_title']);
        $this->assertSame(101, $result[0]['variant_id']);
    }

    public function testGetCartItemsForOrderUsesChildrenItemsAndBundleItems(): void
    {
        $helper = $this->createCartItemsHelper();
        $helper->method('getItemOptions')->willReturn('Bundle');

        $skuProduct = $this->createPartialMockWithReflection(
            Product::class,
            ['getProductUrl', 'getCategoryIds', 'getCustomAttribute', 'getSku']
        );
        $skuProduct->method('getProductUrl')->willReturn('https://x.test/sku');
        $skuProduct->method('getCategoryIds')->willReturn([1]);
        $skuProduct->method('getCustomAttribute')->willReturn(null);
        $skuProduct->method('getSku')->willReturn('BUNDLE-1');
        $helper->method('getProductBySku')->willReturn($skuProduct);

        $childOrderItem = $this->createPartialMockWithReflection(OrderItem::class, [
            'getParentItemId', 'getCreatedAt', 'getUpdatedAt', 'getName', 'getBasePrice',
            'getQtyOrdered', 'getSku', 'getProductId',
        ]);
        $childOrderItem->method('getName')->willReturn('Bundle Selection');
        $childOrderItem->method('getBasePrice')->willReturn(15.0);
        $childOrderItem->method('getQtyOrdered')->willReturn(1.0);
        $childOrderItem->method('getProductId')->willReturn(201);

        $orderItem = $this->createPartialMockWithReflection(OrderItem::class, [
            'getParentItemId', 'getCreatedAt', 'getUpdatedAt', 'getName', 'getBasePrice',
            'getBaseTaxAmount', 'getQtyOrdered', 'getSku', 'getProductId',
            'getHasChildren', 'getChildrenItems', 'getData',
        ]);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getCreatedAt')->willReturn('2026-01-01 00:00:00');
        $orderItem->method('getUpdatedAt')->willReturn('2026-01-02 00:00:00');
        $orderItem->method('getName')->willReturn('Bundle');
        $orderItem->method('getBasePrice')->willReturn(50.0);
        $orderItem->method('getBaseTaxAmount')->willReturn(5.0);
        $orderItem->method('getQtyOrdered')->willReturn(1.0);
        $orderItem->method('getSku')->willReturn('BUNDLE-1');
        $orderItem->method('getProductId')->willReturn(200);
        $orderItem->method('getHasChildren')->willReturn(true);
        $orderItem->method('getChildrenItems')->willReturn([$childOrderItem]);
        $orderItem->method('getData')->with('product_type')->willReturn(Type::TYPE_BUNDLE);

        $order = $this->createPartialMockWithReflection(Order::class, ['getAllItems']);
        $order->method('getAllItems')->willReturn([$orderItem]);

        $result = $helper->getCartItems($order);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('line_price', $result[0]);
        $this->assertCount(1, $result[0]['bundle_items']);
        $this->assertSame('Bundle Selection', $result[0]['bundle_items'][0]['name']);
    }

    public function testGetCartItemsSkipsChildItems(): void
    {
        $helper = $this->createCartItemsHelper();
        $helper->method('getProductBySku')->willReturn(
            $this->createPartialMockWithReflection(Product::class, ['getProductUrl', 'getCategoryIds', 'getCustomAttribute'])
        );

        $childItem = $this->createQuoteCartItem(['parentItemId' => 1]);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getAllItems']);
        $quote->method('getAllItems')->willReturn([$childItem]);

        $this->assertSame([], $helper->getCartItems($quote));
    }

    public function testGetCartItemsSetsVendorWhenCustomAttributeIsObject(): void
    {
        $helper = $this->createCartItemsHelper();

        $vendorAttribute = new DataObject(['value' => 'Nike']);
        $skuProduct       = $this->createPartialMockWithReflection(
            Product::class,
            ['getProductUrl', 'getCategoryIds', 'getCustomAttribute', 'getAttributeText']
        );
        $skuProduct->method('getProductUrl')->willReturn('https://x.test/sku');
        $skuProduct->method('getCategoryIds')->willReturn([1]);
        $skuProduct->method('getCustomAttribute')->willReturn($vendorAttribute);
        $skuProduct->method('getAttributeText')->willReturn('Nike');
        $helper->method('getProductBySku')->willReturn($skuProduct);

        $item = $this->createQuoteCartItem([]);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getAllItems']);
        $quote->method('getAllItems')->willReturn([$item]);

        $result = $helper->getCartItems($quote);

        $this->assertSame('Nike', $result[0]['vendor']);
    }

    public function testGetCartItemsSetsEmptyVendorWhenCustomAttributeAbsent(): void
    {
        $helper = $this->createCartItemsHelper();

        $skuProduct = $this->createPartialMockWithReflection(
            Product::class,
            ['getProductUrl', 'getCategoryIds', 'getCustomAttribute']
        );
        $skuProduct->method('getProductUrl')->willReturn('https://x.test/sku');
        $skuProduct->method('getCategoryIds')->willReturn([1]);
        $skuProduct->method('getCustomAttribute')->willReturn(null);
        $helper->method('getProductBySku')->willReturn($skuProduct);

        $item = $this->createQuoteCartItem([]);

        $quote = $this->createPartialMockWithReflection(Quote::class, ['getAllItems']);
        $quote->method('getAllItems')->willReturn([$item]);

        $result = $helper->getCartItems($quote);

        $this->assertSame('', $result[0]['vendor']);
    }


    public function testGetProductImageReturnsPlaceholderWhenNoSmallImage(): void
    {
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $product->setData('small_image', '');

        $this->assertSame(
            'https://cdn1.avada.io/email-marketing/placeholder-image.gif',
            $this->helper->getProductImage($product)
        );
    }

    public function testGetProductImagePrependsSlashWhenMissing(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getBaseUrl')->willReturn('https://x.test/media/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->setProperty('storeManager', $storeManager);

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $product->setData('small_image', 'img.jpg');

        $this->assertSame('https://x.test/media/catalog/product/img.jpg', $this->helper->getProductImage($product));
    }

    public function testGetProductImageUsesImageAsIsWhenSlashPresent(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getBaseUrl')->willReturn('https://x.test/media/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->setProperty('storeManager', $storeManager);

        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $product->setData('small_image', '/img.jpg');

        $this->assertSame('https://x.test/media/catalog/product/img.jpg', $this->helper->getProductImage($product));
    }


    private function createSendRequestHelper(): array
    {
        $helper = $this->partialHelper(['setHeaders']);
        $helper->method('setHeaders')->willReturn('{}');

        $curlFactory = $this->createMock(CurlFactory::class);
        $curl        = $this->createMock(Curl::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        return [$helper, $curl];
    }

    public function testSendRequestReturnsRawBodyWhenIsLogTrue(): void
    {
        [$helper, $curl] = $this->createSendRequestHelper();
        $curl->method('getBody')->willReturn('{"message":"x"}');

        $this->assertSame(['message' => 'x'], $helper->sendRequest([], '', '', '', false, true));
    }

    public function testSendRequestReturnsBodyOnSuccess(): void
    {
        [$helper, $curl] = $this->createSendRequestHelper();
        $curl->method('getBody')->willReturn('{"success":true,"id":1}');

        $this->assertSame(['success' => true, 'id' => 1], $helper->sendRequest([]));
    }

    public function testSendRequestThrowsLocalizedExceptionOnFailureWithMessage(): void
    {
        [$helper, $curl] = $this->createSendRequestHelper();
        $curl->method('getBody')->willReturn('{"success":false,"message":"bad"}');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error : bad');

        $helper->sendRequest([]);
    }

    public function testSendRequestThrowsLocalizedExceptionWithEmptyMessageWhenMissingSuccessKey(): void
    {
        [$helper, $curl] = $this->createSendRequestHelper();
        $curl->method('getBody')->willReturn('{"foo":"bar"}');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error : ');

        $helper->sendRequest([]);
    }


    public function testSetHeadersDefaultsUrlWhenNoneGiven(): void
    {
        $curl = $this->createMock(Curl::class);
        $this->setProperty('_curl', $curl);

        $helper = $this->partialHelper(['getSecretKey', 'getAppID', 'getConnectToken', 'getMagentoVersion', 'getSMTPVersion']);
        $helper->method('getSecretKey')->willReturn('key');
        $helper->method('getAppID')->willReturn('app');
        $helper->method('getConnectToken')->willReturn('token');
        $helper->method('getMagentoVersion')->willReturn('2.4.9');
        $helper->method('getSMTPVersion')->willReturn('4.2.1');
        $ref = new ReflectionProperty(EmailMarketing::class, '_curl');
        $ref->setValue($helper, $curl);

        $ref = new ReflectionProperty(EmailMarketing::class, 'storeId');
        $ref->setValue($helper, 1);

        $helper->setHeaders(['id' => 1]);

        $urlRef = new ReflectionProperty(EmailMarketing::class, 'url');
        $this->assertSame(EmailMarketing::APP_URL, $urlRef->getValue($helper));
    }

    public function testSetHeadersUsesGetSecretKeyWhenNoneGiven(): void
    {
        $curl   = $this->createMock(Curl::class);
        $helper = $this->partialHelper(['getSecretKey', 'getAppID', 'getConnectToken', 'getMagentoVersion', 'getSMTPVersion']);
        $ref    = new ReflectionProperty(EmailMarketing::class, '_curl');
        $ref->setValue($helper, $curl);
        $storeIdRef = new ReflectionProperty(EmailMarketing::class, 'storeId');
        $storeIdRef->setValue($helper, 1);

        $helper->expects($this->once())->method('getSecretKey')->with(1)->willReturn('resolved-key');
        $helper->method('getAppID')->willReturn('app');
        $helper->method('getConnectToken')->willReturn('token');
        $helper->method('getMagentoVersion')->willReturn('2.4.9');
        $helper->method('getSMTPVersion')->willReturn('4.2.1');

        $helper->setHeaders(['id' => 1]);
    }

    public function testSetHeadersUsesGivenSecretKeyDirectly(): void
    {
        $curl   = $this->createMock(Curl::class);
        $helper = $this->partialHelper(['getSecretKey', 'getAppID', 'getConnectToken', 'getMagentoVersion', 'getSMTPVersion']);
        $ref    = new ReflectionProperty(EmailMarketing::class, '_curl');
        $ref->setValue($helper, $curl);
        $storeIdRef = new ReflectionProperty(EmailMarketing::class, 'storeId');
        $storeIdRef->setValue($helper, 1);

        $helper->expects($this->never())->method('getSecretKey');
        $helper->method('getAppID')->willReturn('app');
        $helper->method('getConnectToken')->willReturn('token');
        $helper->method('getMagentoVersion')->willReturn('2.4.9');
        $helper->method('getSMTPVersion')->willReturn('4.2.1');

        $helper->setHeaders(['id' => 1], '', '', 'given-key');
    }

    public function testSetHeadersAddsAllRequiredHeaders(): void
    {
        $curl   = $this->createMock(Curl::class);
        $curl->expects($this->exactly(7))->method('addHeader');
        $helper = $this->partialHelper(['getSecretKey', 'getAppID', 'getConnectToken', 'getMagentoVersion', 'getSMTPVersion']);
        $ref    = new ReflectionProperty(EmailMarketing::class, '_curl');
        $ref->setValue($helper, $curl);
        $storeIdRef = new ReflectionProperty(EmailMarketing::class, 'storeId');
        $storeIdRef->setValue($helper, 1);

        $helper->method('getAppID')->willReturn('app');
        $helper->method('getConnectToken')->willReturn('token');
        $helper->method('getMagentoVersion')->willReturn('2.4.9');
        $helper->method('getSMTPVersion')->willReturn('4.2.1');

        $helper->setHeaders(['id' => 1], '', '', 'given-key');
    }


    public function testGetSMTPVersionCachesAfterFirstCall(): void
    {
        $helper = $this->partialHelper(['getModuleVersion']);
        $helper->expects($this->once())->method('getModuleVersion')->with('Mageplaza_Smtp')->willReturn('4.2.1');

        $this->assertSame('4.2.1', $helper->getSMTPVersion());
        $this->assertSame('4.2.1', $helper->getSMTPVersion());
    }

    public function testGetModuleVersionReturnsComposerVersion(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->with(ComponentRegistrar::MODULE, 'Mageplaza_Smtp')->willReturn('/path');
        $this->setProperty('componentRegistrar', $registrar);

        $read = $this->createMock(ReadInterface::class);
        $read->method('readFile')->with('composer.json')->willReturn('{"version":"4.2.1"}');

        $readFactory = $this->createMock(ReadFactory::class);
        $readFactory->method('create')->with('/path')->willReturn($read);
        $this->setProperty('readFactory', $readFactory);

        $this->assertSame('4.2.1', $this->helper->getModuleVersion('Mageplaza_Smtp'));
    }

    public function testGetModuleVersionReturnsUnknownWhenNoVersionKey(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn('/path');
        $this->setProperty('componentRegistrar', $registrar);

        $read = $this->createMock(ReadInterface::class);
        $read->method('readFile')->willReturn('{}');

        $readFactory = $this->createMock(ReadFactory::class);
        $readFactory->method('create')->willReturn($read);
        $this->setProperty('readFactory', $readFactory);

        $this->assertEquals(__('UNKNOWN'), $this->helper->getModuleVersion('Mageplaza_Smtp'));
    }

    public function testGetModuleVersionReturnsErrorOnException(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willThrowException(new \Exception('missing module'));
        $this->setProperty('componentRegistrar', $registrar);

        $this->assertSame('error', $this->helper->getModuleVersion('Mageplaza_Smtp'));
    }

    public function testGetMagentoVersionDelegatesToObjectManager(): void
    {
        $productMetadata = $this->createMock(ProductMetadataInterface::class);
        $productMetadata->method('getVersion')->willReturn('2.4.9');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->with(ProductMetadataInterface::class)->willReturn($productMetadata);
        $this->setProperty('objectManager', $objectManager);

        $this->assertSame('2.4.9', $this->helper->getMagentoVersion());
    }


    public function testGetStoreIdUsesStoreManagerWhenNotSet(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())->method('getStore')->willReturn($store);
        $this->setProperty('storeManager', $storeManager);

        $this->assertSame(1, $this->helper->getStoreId());
    }

    public function testGetStoreIdReturnsCachedValueWhenSet(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');
        $this->setProperty('storeManager', $storeManager);
        $this->setProperty('storeId', 5);

        $this->assertSame(5, $this->helper->getStoreId());
    }


    public function testSendRequestWithoutWaitResponseSendsPostWithTimeout(): void
    {
        $helper = $this->partialHelper(['setHeaders']);
        $helper->method('setHeaders')->willReturn('{}');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('setOption')->with(CURLOPT_TIMEOUT_MS, 500);
        $curl->expects($this->once())->method('post');

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        $helper->sendRequestWithoutWaitResponse(['id' => 1]);
    }

    public function testSendRequestWithoutWaitResponseCatchesExceptionAndLogs(): void
    {
        $helper = $this->partialHelper(['setHeaders']);
        $helper->method('setHeaders')->willThrowException(new \Exception('timeout'));

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($this->createMock(Curl::class));
        $this->setProperty('curlFactory', $curlFactory, $helper);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical')->with('timeout');
        $ref = new ReflectionProperty(EmailMarketing::class, '_logger');
        $ref->setValue($helper, $logger);

        $helper->sendRequestWithoutWaitResponse(['id' => 1]);
    }


    public function testDeleteQuoteSendsDeleteRequest(): void
    {
        $helper = $this->partialHelper(['getSecretKey', 'getAppID']);
        $helper->method('getSecretKey')->willReturn('key');
        $helper->method('getAppID')->willReturn('app');

        // deleteQuote() (EmailMarketing.php:1426-1427) calls setOption() twice —
        // (CURLOPT_POST, null) then (CURLOPT_CUSTOMREQUEST, 'DELETE'); a single
        // once()+with() expectation only tolerates one of the two calls.
        $setOptionCalls = [];
        $curl = $this->createMock(Curl::class);
        $curl->method('setOption')->willReturnCallback(
            function ($option, $value) use (&$setOptionCalls) {
                $setOptionCalls[] = [$option, $value];
            }
        );
        $curl->expects($this->once())->method('post')->with(EmailMarketing::DELETE_URL . 5, []);

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        $helper->deleteQuote(5, 1);

        $this->assertContains([CURLOPT_POST, null], $setOptionCalls);
        $this->assertContains([CURLOPT_CUSTOMREQUEST, 'DELETE'], $setOptionCalls);
    }


    public function testIsSubscriberTrueForSubscribedStatus(): void
    {
        $this->assertTrue($this->helper->isSubscriber(Subscriber::STATUS_SUBSCRIBED));
    }

    public function testIsSubscriberFalseForOtherStatus(): void
    {
        $this->assertFalse($this->helper->isSubscriber(0));
    }


    public function testGetTagsJoinsStoreWebsiteAndGroupCode(): void
    {
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getName')->willReturn('Main Store');

        $website = $this->createMock(\Magento\Store\Model\Website::class);
        $website->method('getName')->willReturn('Main Website');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);
        $this->setProperty('storeManager', $storeManager);

        $group = $this->createPartialMockWithReflection(Group::class, ['load', 'getCustomerGroupCode']);
        $group->method('getCustomerGroupCode')->willReturn('General');

        $groupFactory = $this->createPartialMock(GroupFactory::class, ['create']);
        $groupFactory->method('create')->willReturn($group);
        $this->setProperty('customerGroupFactory', $groupFactory);

        $customer = $this->createPartialMockWithReflection(Customer::class, ['getStoreId', 'getWebsiteId', 'getGroupId']);
        $customer->method('getStoreId')->willReturn(1);
        $customer->method('getWebsiteId')->willReturn(1);
        $customer->method('getGroupId')->willReturn(1);

        $this->assertSame('Main Store,Main Website,General', $this->helper->getTags($customer));
    }


    private function createCustomerDataHelper(): EmailMarketing&MockObject
    {
        $helper = $this->partialHelper([
            'getTags', 'formatDate', 'getLifetimeSales', 'getBaseCurrencyByWebsiteId',
        ]);
        $helper->method('getTags')->willReturn('tags');
        $helper->method('formatDate')->willReturnArgument(0);

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $this->setProperty('_localeDate', $localeDate, $helper);

        return $helper;
    }

    private function createDataCustomer(array $methods): Customer&MockObject
    {
        return $this->createPartialMockWithReflection(Customer::class, array_unique(array_merge($methods, [
            'getId', 'getEmail', 'getFirstname', 'getLastname', 'getGender', 'getDob',
            'getCreatedAt', 'getUpdatedAt', 'getStoreId', 'getData', 'getIsSubscribed',
            'getDefaultBillingAddress',
        ])));
    }

    public function testGetCustomerDataWithSubscriberLookup(): void
    {
        $helper = $this->createCustomerDataHelper();

        $subscriber = $this->createPartialMockWithReflection(Subscriber::class, ['loadByEmail', 'getSubscriberStatus']);
        $subscriber->method('loadByEmail')->willReturnSelf();
        $subscriber->method('getSubscriberStatus')->willReturn(Subscriber::STATUS_SUBSCRIBED);

        $subscriberFactory = $this->createPartialMock(SubscriberFactory::class, ['create']);
        $subscriberFactory->method('create')->willReturn($subscriber);
        $this->setProperty('_subscriberFactory', $subscriberFactory, $helper);

        $customer = $this->createDataCustomer([]);
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getDefaultBillingAddress')->willReturn(null);

        $result = $helper->getCustomerData($customer, true);

        $this->assertTrue($result['isSubscriber']);
    }

    public function testGetCustomerDataWithoutSubscriberLookup(): void
    {
        $helper = $this->createCustomerDataHelper();

        $customer = $this->createDataCustomer([]);
        $customer->method('getData')->with('subscriber_status')->willReturn(0);
        $customer->method('getIsSubscribed')->willReturn(true);
        $customer->method('getDefaultBillingAddress')->willReturn(null);

        $result = $helper->getCustomerData($customer, false);

        $this->assertTrue($result['isSubscriber']);
    }

    public function testGetCustomerDataWithDefaultBillingAddress(): void
    {
        $helper = $this->createCustomerDataHelper();

        $renderer = $this->createMock(RendererInterface::class);
        $renderer->method('renderArray')->willReturn('123 Main St');

        $formatObj = new DataObject(['renderer' => $renderer]);
        $addressConfig = $this->createMock(AddressConfig::class);
        $addressConfig->method('getFormatByCode')->with(ElementFactory::OUTPUT_FORMAT_ONELINE)->willReturn($formatObj);
        $this->setProperty('_addressConfig', $addressConfig, $helper);

        $country = $this->createPartialMockWithReflection(Country::class, ['loadByCode', 'getName']);
        $country->method('loadByCode')->willReturnSelf();
        $country->method('getName')->willReturn('United States');

        $countryFactory = $this->createPartialMock(CountryFactory::class, ['create']);
        $countryFactory->method('create')->willReturn($country);
        $this->setProperty('countryFactory', $countryFactory, $helper);

        $billingAddress = $this->createPartialMockWithReflection(
            \Magento\Customer\Model\Address::class,
            ['getCountryId', 'getCity', 'getTelephone', 'getData']
        );
        $billingAddress->method('getCountryId')->willReturn('US');
        $billingAddress->method('getCity')->willReturn('New York');
        $billingAddress->method('getTelephone')->willReturn('0123456789');
        $billingAddress->method('getData')->willReturn([]);

        $customer = $this->createDataCustomer([]);
        $customer->method('getDefaultBillingAddress')->willReturn($billingAddress);

        $result = $helper->getCustomerData($customer);

        $this->assertSame('US', $result['countryCode']);
        $this->assertSame('United States', $result['country']);
        $this->assertSame('New York', $result['city']);
        $this->assertSame('123 Main St', $result['address']);
    }

    public function testGetCustomerDataWithoutDefaultBillingAddress(): void
    {
        $helper = $this->createCustomerDataHelper();

        $customer = $this->createDataCustomer([]);
        $customer->method('getDefaultBillingAddress')->willReturn(null);

        $result = $helper->getCustomerData($customer);

        $this->assertArrayNotHasKey('countryCode', $result);
        $this->assertArrayNotHasKey('country', $result);
    }

    public function testGetCustomerDataWithUpdateOrderPopulatesOrderStats(): void
    {
        $helper = $this->createCustomerDataHelper();
        $helper->method('getLifetimeSales')->willReturn(150.0);

        $currency = $this->createMock(Currency::class);
        $currency->method('getCurrencyCode')->willReturn('USD');
        $helper->method('getBaseCurrencyByWebsiteId')->willReturn($currency);

        $orderItem = new DataObject(['id' => 99]);
        $filteredCollection = $this->createMock(OrderCollection::class);
        $filteredCollection->method('addFieldToFilter')->willReturnSelf();
        $filteredCollection->method('getSize')->willReturn(3);
        $filteredCollection->method('addOrder')->willReturnSelf();
        $filteredCollection->method('getFirstItem')->willReturn($orderItem);

        $orderCollection = $this->createMock(OrderCollection::class);
        $orderCollection->method('addFieldToFilter')->willReturn($filteredCollection);
        $this->setProperty('orderCollection', $orderCollection, $helper);

        $customer = $this->createDataCustomer(['getWebsiteId']);
        $customer->method('getDefaultBillingAddress')->willReturn(null);
        $customer->method('getWebsiteId')->willReturn(1);

        $result = $helper->getCustomerData($customer, false, true);

        $this->assertSame(3, $result['orders_count']);
        $this->assertSame(99, $result['last_order_id']);
        $this->assertSame(150.0, $result['total_spent']);
        $this->assertSame('USD', $result['currency']);
    }

    public function testGetCustomerDataWithoutUpdateOrderOmitsOrderStats(): void
    {
        $helper = $this->createCustomerDataHelper();

        $customer = $this->createDataCustomer([]);
        $customer->method('getDefaultBillingAddress')->willReturn(null);

        $result = $helper->getCustomerData($customer, false, false);

        $this->assertArrayNotHasKey('orders_count', $result);
        $this->assertArrayNotHasKey('total_spent', $result);
    }


    public function testGetLifetimeSalesBuildsExpressionAndReturnsLifetime(): void
    {
        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getStateStatuses')->willReturn(['canceled']);
        $this->setProperty('orderConfig', $orderConfig);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('getIfNullSql')->willReturnCallback(
            static fn (string $expr, $value) => $expr
        );

        $select = $this->createMock(Select::class);
        $select->method('columns')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $item = new DataObject(['lifetime' => 250.5]);
        $collection = $this->createMock(ReportOrderCollection::class);
        $collection->method('setMainTable')->willReturnSelf();
        $collection->method('removeAllFieldsFromSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getFirstItem')->willReturn($item);

        $reportCollectionFactory = $this->createPartialMock(ReportOrderCollectionFactory::class, ['create']);
        $reportCollectionFactory->method('create')->willReturn($collection);
        $this->setProperty('reportCollectionFactory', $reportCollectionFactory);

        $this->assertSame(250.5, $this->helper->getLifetimeSales(9));
    }


    public function testGetCustomerByIdDelegatesToFactory(): void
    {
        $customer = $this->createMock(Customer::class);

        $customerFactory = $this->createPartialMock(CustomerFactory::class, ['create']);
        $customerFactory->method('create')->willReturn($customer);
        $this->setProperty('customerFactory', $customerFactory);

        $resource = $this->createMock(\Magento\Customer\Model\ResourceModel\Customer::class);
        $resource->expects($this->once())->method('load')->with($customer, 5);
        $this->setProperty('customerResource', $resource);

        $this->assertSame($customer, $this->helper->getCustomerById(5));
    }


    public function testUpdateCustomerNoOpWhenIdFalsy(): void
    {
        $helper = $this->partialHelper(['getCustomerById', 'getCustomerData', 'syncCustomer']);
        $helper->expects($this->never())->method('getCustomerById');

        $helper->updateCustomer(0);
    }

    public function testUpdateCustomerSyncsCustomerDataOnSuccess(): void
    {
        $helper   = $this->partialHelper(['getCustomerById', 'getCustomerData', 'syncCustomer']);
        $customer = $this->createMock(Customer::class);
        $helper->expects($this->once())->method('getCustomerById')->with(5)->willReturn($customer);
        $helper->expects($this->once())->method('getCustomerData')->with($customer, true, true)->willReturn(['id' => 5]);
        $helper->expects($this->once())->method('syncCustomer')->with(['id' => 5], false);

        $helper->updateCustomer(5);
    }

    public function testUpdateCustomerCatchesExceptionAndLogs(): void
    {
        $helper = $this->partialHelper(['getCustomerById', 'getCustomerData', 'syncCustomer']);
        $helper->method('getCustomerById')->willThrowException(new \Exception('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical')->with('boom');
        $this->setProperty('logger', $logger, $helper);

        $helper->updateCustomer(5);
    }


    public function testGetBaseCurrencyByWebsiteIdDelegatesToStoreManager(): void
    {
        $currency = $this->createMock(Currency::class);
        $website  = $this->createMock(\Magento\Store\Model\Website::class);
        $website->method('getBaseCurrency')->willReturn($currency);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->with(1)->willReturn($website);
        $this->setProperty('storeManager', $storeManager);

        $this->assertSame($currency, $this->helper->getBaseCurrencyByWebsiteId(1));
    }


    public function testTestConnectionResolvesRealSecretWhenMasked(): void
    {
        $helper = $this->partialHelper(['getSecretKey', 'getStoreInformation', 'sendRequest']);
        $helper->expects($this->once())->method('getSecretKey')->willReturn('resolved-key');
        $helper->method('getStoreInformation')->willReturn(['name' => 'Store']);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with(['name' => 'Store'], '', 'app-id', 'resolved-key')
            ->willReturn(['success' => true]);

        $helper->testConnection('app-id', '******');
    }

    public function testTestConnectionUsesGivenSecretDirectly(): void
    {
        $helper = $this->partialHelper(['getSecretKey', 'getStoreInformation', 'sendRequest']);
        $helper->expects($this->never())->method('getSecretKey');
        $helper->method('getStoreInformation')->willReturn(['name' => 'Store']);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with(['name' => 'Store'], '', 'app-id', 'realkey')
            ->willReturn(['success' => true]);

        $helper->testConnection('app-id', 'realkey');
    }


    private function createStoreInformationHelper(): array
    {
        $helper = $this->partialHelper(['getConfigData', 'getContactCount']);
        // getConfigData() is deliberately left unstubbed here — every caller below
        // configures its own matcher; a shared base stub would register a second
        // matcher that always wins (or throws on a mismatched with()) since
        // InvocationHandler::invoke() runs every registered matcher per call.
        $helper->method('getContactCount')->willReturn(5);

        $localeDate = $this->createMock(TimezoneInterface::class);
        $localeDate->method('getConfigTimezone')->willReturn('UTC');
        $this->setProperty('_localeDate', $localeDate, $helper);

        $orderCollection = $this->createMock(OrderCollection::class);
        $orderCollection->method('getSize')->willReturn(10);
        $this->setProperty('orderCollection', $orderCollection, $helper);

        $abandonedCartCollection = $this->createMock(Collection::class);
        $abandonedCartCollection->method('getSize')->willReturn(2);
        $this->setProperty('abandonedCartCollection', $abandonedCartCollection, $helper);

        $request = $this->createMock(\Magento\Framework\App\RequestInterface::class);
        $ref     = new ReflectionProperty(EmailMarketing::class, '_request');
        $ref->setValue($helper, $request);

        return [$helper, $request];
    }

    public function testGetStoreInformationForStoreScope(): void
    {
        [$helper, $request] = $this->createStoreInformationHelper();
        $request->method('getParam')->willReturnMap([['store', null, '2'], ['website', null, null]]);

        // Two of getConfigData()'s 9 call sites (currency, email — EmailMarketing.php:1675,
        // 1678) omit $scope/$scopeCode and fall back to the method's own defaults, so a
        // single with() constraint applied to every call would throw on those; capture
        // instead and assert the expected scope/code appear among the calls.
        $calls = [];
        $helper->method('getConfigData')->willReturnCallback(
            function (string $path, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES, $scopeCode = null) use (&$calls) {
                $calls[] = [$scope, $scopeCode];

                return '';
            }
        );

        $helper->getStoreInformation();

        $this->assertContains([\Magento\Store\Model\ScopeInterface::SCOPE_STORES, '2'], $calls);
    }

    public function testGetStoreInformationForWebsiteScope(): void
    {
        [$helper, $request] = $this->createStoreInformationHelper();
        $request->method('getParam')->willReturnMap([['store', null, null], ['website', null, 'base']]);

        $calls = [];
        $helper->method('getConfigData')->willReturnCallback(
            function (string $path, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES, $scopeCode = null) use (&$calls) {
                $calls[] = [$scope, $scopeCode];

                return '';
            }
        );

        $helper->getStoreInformation();

        $this->assertContains([\Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES, 'base'], $calls);
    }

    public function testGetStoreInformationForDefaultScope(): void
    {
        [$helper, $request] = $this->createStoreInformationHelper();
        $request->method('getParam')->willReturnMap([['store', null, null], ['website', null, null]]);

        $calls = [];
        $helper->method('getConfigData')->willReturnCallback(
            function (string $path, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES, $scopeCode = null) use (&$calls) {
                $calls[] = [$scope, $scopeCode];

                return '';
            }
        );

        $helper->getStoreInformation();

        $this->assertContains(
            [\Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null],
            $calls
        );
    }

    public function testGetStoreInformationResolvesCountryNameWhenCountryCodeSet(): void
    {
        [$helper, $request] = $this->createStoreInformationHelper();
        $request->method('getParam')->willReturnMap([['store', null, null], ['website', null, null]]);
        $helper->method('getConfigData')->willReturnCallback(
            static fn (string $path) => $path === Information::XML_PATH_STORE_INFO_COUNTRY_CODE ? 'US' : ''
        );

        $country = $this->createPartialMockWithReflection(Country::class, ['loadByCode', 'getName']);
        $country->method('loadByCode')->willReturnSelf();
        $country->method('getName')->willReturn('United States');

        $countryFactory = $this->createPartialMock(CountryFactory::class, ['create']);
        $countryFactory->method('create')->willReturn($country);
        $this->setProperty('countryFactory', $countryFactory, $helper);

        $result = $helper->getStoreInformation();

        $this->assertSame('United States', $result['countryName']);
    }

    public function testGetStoreInformationOmitsCountryNameWhenNoCountryCode(): void
    {
        [$helper, $request] = $this->createStoreInformationHelper();
        $request->method('getParam')->willReturnMap([['store', null, null], ['website', null, null]]);
        $helper->method('getConfigData')->willReturn('');

        $result = $helper->getStoreInformation();

        $this->assertArrayNotHasKey('countryName', $result);
    }


    public function testGetConfigDataDelegatesToScopeConfig(): void
    {
        $scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('path/to/value', \Magento\Store\Model\ScopeInterface::SCOPE_STORES, 1)
            ->willReturn('configured');
        $this->setProperty('scopeConfig', $scopeConfig);

        $this->assertSame('configured', $this->helper->getConfigData('path/to/value', \Magento\Store\Model\ScopeInterface::SCOPE_STORES, 1));
    }


    public function testSyncCustomerSetsCreateFlag(): void
    {
        $helper = $this->partialHelper(['sendRequest']);
        $helper->expects($this->once())->method('sendRequest')->with(['id' => 1], EmailMarketing::CUSTOMER_URL)->willReturn(['success' => true]);

        $helper->syncCustomer(['id' => 1], true);

        $ref = new ReflectionProperty(EmailMarketing::class, 'isPUT');
        $this->assertFalse($ref->getValue($helper));
    }

    public function testSyncCustomerSetsUpdateFlag(): void
    {
        $helper = $this->partialHelper(['sendRequest']);
        $helper->method('sendRequest')->willReturn(['success' => true]);

        $helper->syncCustomer(['id' => 1], false);

        $ref = new ReflectionProperty(EmailMarketing::class, 'isPUT');
        $this->assertTrue($ref->getValue($helper));
    }

    public function testSyncCustomersSendsBulkRequest(): void
    {
        $helper = $this->partialHelper(['sendRequest']);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with(['a' => 1], EmailMarketing::SYNC_CUSTOMER_URL, '', '', false, true)
            ->willReturn(['ok' => true]);

        $helper->syncCustomers(['a' => 1]);
    }

    public function testSyncOrdersSendsBulkRequest(): void
    {
        $helper = $this->partialHelper(['sendRequest']);
        $helper->expects($this->once())
            ->method('sendRequest')
            ->with(['a' => 1], EmailMarketing::SYNC_ORDER_URL, '', '', false, true)
            ->willReturn(['ok' => true]);

        $helper->syncOrders(['a' => 1]);
    }


    public function testGetSyncedAttributeReturnsAttributeWhenFound(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getId')->willReturn(15);

        $customerAttribute = $this->createMock(Attribute::class);
        $customerAttribute->method('loadByCode')->with('customer', EmailMarketing::IS_SYNCED_ATTRIBUTE)->willReturn($attribute);
        $this->setProperty('customerAttribute', $customerAttribute);

        $this->assertSame($attribute, $this->helper->getSyncedAttribute());
    }

    public function testGetSyncedAttributeThrowsWhenNotFound(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getId')->willReturn(0);

        $customerAttribute = $this->createMock(Attribute::class);
        $customerAttribute->method('loadByCode')->willReturn($attribute);
        $this->setProperty('customerAttribute', $customerAttribute);

        $this->expectException(LocalizedException::class);

        $this->helper->getSyncedAttribute();
    }


    public function testSetIsSyncedCustomerRoundTrip(): void
    {
        $this->helper->setIsSyncedCustomer(true);

        $this->assertTrue($this->helper->isSyncedCustomer());
    }


    public function testSendRequestProxyPostMethod(): void
    {
        $helper = $this->partialHelper(['setHeaders']);
        $helper->method('setHeaders')->willReturn('{"data":1}');

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('post')->with('https://x.test/proxy', '{"data":1}');
        $curl->method('getBody')->willReturn('{"ok":true}');

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        // getMethod() is declared on \Laminas\Http\Request (base of
        // \Magento\Framework\App\Request\Http), NOT on RequestInterface —
        // createMock(RequestInterface::class) cannot configure it.
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $request->method('getParams')->willReturn([]);
        $request->method('getMethod')->willReturn('POST');
        $ref = new ReflectionProperty(EmailMarketing::class, '_request');
        $ref->setValue($helper, $request);

        $this->assertSame(['ok' => true], $helper->sendRequestProxy('https://x.test/proxy', ['data' => 1]));
    }

    public function testSendRequestProxyGetMethod(): void
    {
        $helper = $this->partialHelper([]);

        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('get')->with('https://x.test/proxy');
        $curl->method('getBody')->willReturn('{"ok":true}');

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        // getMethod() is declared on \Laminas\Http\Request (base of
        // \Magento\Framework\App\Request\Http), NOT on RequestInterface —
        // createMock(RequestInterface::class) cannot configure it.
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $request->method('getParams')->willReturn([]);
        $request->method('getMethod')->willReturn('GET');
        $ref = new ReflectionProperty(EmailMarketing::class, '_request');
        $ref->setValue($helper, $request);

        $this->assertSame(['ok' => true], $helper->sendRequestProxy('https://x.test/proxy', null));
    }

    public function testSendRequestProxyRawTypeParam(): void
    {
        $helper = $this->partialHelper([]);

        $curl = $this->createMock(Curl::class);
        $curl->method('get')->willReturn(null);
        $curl->method('getBody')->willReturn('raw-body');

        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);
        $this->setProperty('curlFactory', $curlFactory, $helper);

        // getMethod() is declared on \Laminas\Http\Request (base of
        // \Magento\Framework\App\Request\Http), NOT on RequestInterface —
        // createMock(RequestInterface::class) cannot configure it.
        $request = $this->createMock(\Magento\Framework\App\Request\Http::class);
        $request->method('getParams')->willReturn(['type' => 'raw']);
        $request->method('getMethod')->willReturn('GET');
        $ref = new ReflectionProperty(EmailMarketing::class, '_request');
        $ref->setValue($helper, $request);

        $this->assertSame('raw-body', $helper->sendRequestProxy('https://x.test/proxy', null));
    }


    public function testGetCategoriesReturnsRowsForGivenIds(): void
    {
        $category1 = $this->createPartialMockWithReflection(Category::class, ['load', 'getId', 'getName']);
        $category1->method('getId')->willReturn(3);
        $category1->method('getName')->willReturn('Category Three');

        $category2 = $this->createPartialMockWithReflection(Category::class, ['load', 'getId', 'getName']);
        $category2->method('getId')->willReturn(4);
        $category2->method('getName')->willReturn('Category Four');

        $categoryFactory = $this->createPartialMock(CategoryFactory::class, ['create']);
        $categoryFactory->method('create')->willReturn($category1, $category2);
        $this->setProperty('categoryFactory', $categoryFactory);

        $result = $this->helper->getCategories([3, 4]);

        $this->assertSame([
            ['category_id' => 3, 'category_name' => 'Category Three'],
            ['category_id' => 4, 'category_name' => 'Category Four'],
        ], $result);
    }

    public function testGetCategoriesReturnsEmptyArrayForNullOrEmpty(): void
    {
        $this->assertSame([], $this->helper->getCategories(null));
        $this->assertSame([], $this->helper->getCategories([]));
    }


    public function testGetParentIdReturnsConfigurableParent(): void
    {
        $configurable = $this->createMock(Configurable::class);
        $configurable->method('getParentIdsByChild')->with(10)->willReturn([1]);
        $this->setProperty('configurable', $configurable);

        $this->assertSame(1, $this->helper->getParentId(10));
    }

    public function testGetParentIdReturnsGroupedParent(): void
    {
        $configurable = $this->createMock(Configurable::class);
        $configurable->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('configurable', $configurable);

        $grouped = $this->createMock(Grouped::class);
        $grouped->method('getParentIdsByChild')->with(10)->willReturn([2]);
        $this->setProperty('grouped', $grouped);

        $this->assertSame(2, $this->helper->getParentId(10));
    }

    public function testGetParentIdReturnsBundleParent(): void
    {
        $configurable = $this->createMock(Configurable::class);
        $configurable->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('configurable', $configurable);

        $grouped = $this->createMock(Grouped::class);
        $grouped->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('grouped', $grouped);

        $bundle = $this->createMock(\Magento\Bundle\Model\Product\Type::class);
        $bundle->method('getParentIdsByChild')->with(10)->willReturn([3]);
        $this->setProperty('bundle', $bundle);

        $this->assertSame(3, $this->helper->getParentId(10));
    }

    public function testGetParentIdReturnsNullWhenNoParentFound(): void
    {
        $configurable = $this->createMock(Configurable::class);
        $configurable->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('configurable', $configurable);

        $grouped = $this->createMock(Grouped::class);
        $grouped->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('grouped', $grouped);

        $bundle = $this->createMock(\Magento\Bundle\Model\Product\Type::class);
        $bundle->method('getParentIdsByChild')->willReturn([]);
        $this->setProperty('bundle', $bundle);

        $this->assertNull($this->helper->getParentId(10));
    }


    public function testGetProductBySkuReturnsVisibleProductDirectly(): void
    {
        $product = $this->createPartialMockWithReflection(Product::class, ['getVisibility']);
        $product->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);

        $item = $this->createPartialMockWithReflection(OrderItem::class, ['getBuyRequest']);
        $item->method('getBuyRequest')->willReturn(null);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('get')->with('SKU-1')->willReturn($product);
        $this->setProperty('productRepository', $productRepository);

        $this->assertSame($product, $this->helper->getProductBySku($item, 'SKU-1'));
    }

    public function testGetProductBySkuResolvesSuperProductConfigProductId(): void
    {
        $notVisible = $this->createPartialMockWithReflection(Product::class, ['getVisibility', 'getId']);
        $notVisible->method('getVisibility')->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $notVisible->method('getId')->willReturn(50);

        $resolved = $this->createPartialMockWithReflection(Product::class, ['getVisibility']);

        $buyRequest = new DataObject(['super_product_config' => ['product_id' => 5]]);
        $item = $this->createPartialMockWithReflection(OrderItem::class, ['getBuyRequest']);
        $item->method('getBuyRequest')->willReturn($buyRequest);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('get')->with('SKU-1')->willReturn($notVisible);
        $productRepository->expects($this->once())->method('getById')->with(5)->willReturn($resolved);
        $this->setProperty('productRepository', $productRepository);

        $this->assertSame($resolved, $this->helper->getProductBySku($item, 'SKU-1'));
    }

    public function testGetProductBySkuResolvesSuperAttributeProductId(): void
    {
        $notVisible = $this->createPartialMockWithReflection(Product::class, ['getVisibility', 'getId']);
        $notVisible->method('getVisibility')->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);

        $resolved = $this->createPartialMockWithReflection(Product::class, ['getVisibility']);

        $buyRequest = new DataObject(['super_attribute' => ['1' => '2'], 'product' => 6]);
        $item = $this->createPartialMockWithReflection(OrderItem::class, ['getBuyRequest']);
        $item->method('getBuyRequest')->willReturn($buyRequest);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('get')->willReturn($notVisible);
        $productRepository->expects($this->once())->method('getById')->with(6)->willReturn($resolved);
        $this->setProperty('productRepository', $productRepository);

        $this->assertSame($resolved, $this->helper->getProductBySku($item, 'SKU-1'));
    }

    public function testGetProductBySkuFallsBackToGetParentId(): void
    {
        $notVisible = $this->createPartialMockWithReflection(Product::class, ['getVisibility', 'getId']);
        $notVisible->method('getVisibility')->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $notVisible->method('getId')->willReturn(50);

        $resolved = $this->createPartialMockWithReflection(Product::class, ['getVisibility']);

        $buyRequest = new DataObject([]);
        $item = $this->createPartialMockWithReflection(OrderItem::class, ['getBuyRequest']);
        $item->method('getBuyRequest')->willReturn($buyRequest);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('get')->willReturn($notVisible);
        $productRepository->expects($this->once())->method('getById')->with(50)->willReturn($resolved);
        $this->setProperty('productRepository', $productRepository);

        $configurable = $this->createMock(Configurable::class);
        $configurable->method('getParentIdsByChild')->with(50)->willReturn([50]);
        $this->setProperty('configurable', $configurable);

        $this->assertSame($resolved, $this->helper->getProductBySku($item, 'SKU-1'));
    }

    public function testGetProductBySkuReturnsEmptyDataObjectOnException(): void
    {
        $item = $this->createMock(OrderItem::class);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('get')->willThrowException(new \Exception('not found'));
        $this->setProperty('productRepository', $productRepository);

        $result = $this->helper->getProductBySku($item, 'MISSING-SKU');

        $this->assertInstanceOf(DataObject::class, $result);
        $this->assertSame([], $result->getData());
    }
}
