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

namespace Mageplaza\Smtp\Test\Unit\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config;
use Mageplaza\Smtp\Block\AbandonedCart;
use Mageplaza\Smtp\Helper\EmailMarketing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbandonedCart::class)]
class AbandonedCartTest extends TestCase
{
    use MockCreationTrait;

    private Context&MockObject $context;
    private ProductRepositoryInterface&MockObject $productRepository;
    private PriceCurrency&MockObject $priceCurrency;
    private QuoteFactory&MockObject $quoteFactory;
    private EmailMarketing&MockObject $helperEmailMarketing;
    private StoreManagerInterface&MockObject $storeManager;
    private TaxHelper&MockObject $taxHelper;

    private AbandonedCart $block;

    private \Magento\Quote\Model\ResourceModel\Quote&MockObject $quoteResource;

    protected function setUp(): void
    {
        $this->quoteResource = $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->taxHelper = $this->createMock(TaxHelper::class);

        $this->context = $this->createMock(Context::class);
        $this->context->method('getTaxData')->willReturn($this->taxHelper);
        $this->context->method('getStoreManager')->willReturn($this->storeManager);

        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->priceCurrency = $this->createMock(PriceCurrency::class);
        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);

        $this->block = new AbandonedCart(
            $this->context,
            $this->productRepository,
            $this->priceCurrency,
            $this->quoteFactory,
            $this->helperEmailMarketing,
            $this->quoteResource
        );
    }

    private function stubQuoteLoad(Quote $loadedQuote, int $quoteId): void
    {
        $this->quoteResource->expects($this->once())->method('load')->with($loadedQuote, $quoteId);
        $this->quoteFactory->method('create')->willReturn($loadedQuote);
    }


    public function testGetQuoteReturnsLoadedQuoteWhenQuoteIdSet(): void
    {
        $loadedQuote = $this->createMock(Quote::class);
        $this->stubQuoteLoad($loadedQuote, 5);

        $this->block->setData('quote_id', 5);

        $this->assertSame($loadedQuote, $this->block->getQuote());
    }

    public function testGetQuoteReturnsNullWhenNoQuoteIdSet(): void
    {
        $this->quoteFactory->expects($this->never())->method('create');

        $this->assertNull($this->block->getQuote());
    }


    public function testGetHelperEmailMarketingReturnsInjectedInstance(): void
    {
        $this->assertSame($this->helperEmailMarketing, $this->block->getHelperEmailMarketing());
    }


    public function testGetProductCollectionReturnsVisibleItemsWhenQuoteExists(): void
    {
        $item1 = $this->createMock(Item::class);
        $item2 = $this->createMock(Item::class);

        $loadedQuote = $this->createMock(Quote::class);
        $loadedQuote->method('getAllVisibleItems')->willReturn([$item1, $item2]);
        $this->stubQuoteLoad($loadedQuote, 5);

        $this->block->setData('quote_id', 5);

        $this->assertSame([$item1, $item2], $this->block->getProductCollection());
    }

    public function testGetProductCollectionReturnsEmptyArrayWhenNoQuote(): void
    {
        $this->assertSame([], $this->block->getProductCollection());
    }


    public function testGetSubtotalUsesShippingAddressSubtotalWhenNonVirtualExclTax(): void
    {
        $address = $this->createPartialMockWithReflection(Address::class, ['getSubtotal']);
        $address->method('getSubtotal')->willReturn(100.0);

        $loadedQuote = $this->createMock(Quote::class);
        $loadedQuote->method('isVirtual')->willReturn(false);
        $loadedQuote->method('getShippingAddress')->willReturn($address);
        $loadedQuote->method('getStoreId')->willReturn(1);
        $this->stubQuoteLoad($loadedQuote, 5);

        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(100.0, true, PriceCurrency::DEFAULT_PRECISION, 1)
            ->willReturn('$100.00');

        $this->block->setData('quote_id', 5);

        $this->assertSame('$100.00', $this->block->getSubtotal());
    }

    public function testGetSubtotalUsesBillingAddressSubtotalInclTaxWhenVirtual(): void
    {
        $address = $this->createPartialMockWithReflection(Address::class, ['getSubtotalInclTax']);
        $address->method('getSubtotalInclTax')->willReturn(120.0);

        $loadedQuote = $this->createMock(Quote::class);
        $loadedQuote->method('isVirtual')->willReturn(true);
        $loadedQuote->method('getBillingAddress')->willReturn($address);
        $loadedQuote->method('getStoreId')->willReturn(2);
        $this->stubQuoteLoad($loadedQuote, 7);

        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(120.0, true, PriceCurrency::DEFAULT_PRECISION, 2)
            ->willReturn('$120.00');

        $this->block->setData('quote_id', 7);

        $this->assertSame('$120.00', $this->block->getSubtotal(true));
    }

    public function testGetSubtotalFormatsZeroWhenNoQuote(): void
    {
        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(0, true, PriceCurrency::DEFAULT_PRECISION, null)
            ->willReturn('$0.00');

        $this->assertSame('$0.00', $this->block->getSubtotal());
    }


    public function testFormatPriceDelegatesToPriceCurrency(): void
    {
        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(50.5, true, PriceCurrency::DEFAULT_PRECISION, 3)
            ->willReturn('$50.50');

        $this->assertSame('$50.50', $this->block->formatPrice(50.5, 3));
    }


    public function testGetProductImageReturnsUrlWithForwardSlashes(): void
    {
        $item = $this->createPartialMockWithReflection(Item::class, ['getProductId']);
        $item->method('getProductId')->willReturn(10);

        $product = $this->createMock(Product::class);
        $product->method('getImage')->willReturn('\\subdir\\img.jpg');
        $this->productRepository->method('getById')->with(10)->willReturn($product);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn('https://x/media/');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->assertSame(
            'https://x/media/catalog/product/subdir/img.jpg',
            $this->block->getProductImage($item)
        );
    }

    public function testGetProductImageReturnsNullWhenProductNotFound(): void
    {
        $item = $this->createPartialMockWithReflection(Item::class, ['getProductId']);
        $item->method('getProductId')->willReturn(99);

        $this->productRepository->method('getById')->with(99)
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $this->assertNull($this->block->getProductImage($item));
    }


    public function testGetTaxConfigDelegatesToTaxHelper(): void
    {
        $config = $this->createMock(Config::class);
        $this->taxHelper->expects($this->once())->method('getConfig')->willReturn($config);

        $this->assertSame($config, $this->block->getTaxConfig());
    }
}
