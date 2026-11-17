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

namespace Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Ui\Component\Listing\Column\CustomerName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerName::class)]
class CustomerNameTest extends TestCase
{
    use MockCreationTrait;

    private ContextInterface&MockObject $context;
    private UiComponentFactory&MockObject $uiComponentFactory;
    private UrlInterface&MockObject $urlBuilder;
    private QuoteFactory&MockObject $quoteFactory;
    private EmailMarketing&MockObject $helper;

    private \Magento\Quote\Model\ResourceModel\Quote&MockObject $quoteResource;

    protected function setUp(): void
    {
        $this->quoteResource = $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->uiComponentFactory = $this->createMock(UiComponentFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->quoteFactory = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->helper = $this->createMock(EmailMarketing::class);
    }

    private function createSut(): CustomerName
    {
        return new CustomerName(
            $this->context,
            $this->uiComponentFactory,
            $this->urlBuilder,
            $this->quoteFactory,
            $this->helper,
            $this->quoteResource,
            [],
            ['name' => 'customer_name']
        );
    }

    // getCustomerId() is magic on Quote (only @method-documented — vendor/magento/module-quote/Model/Quote.php:51).
    // Quote's real constructor chain (AbstractModel::_construct()/_init()) touches ObjectManager::getInstance()
    // internally even with every ctor arg mocked, which isn't bootstrapped in the unit test framework —
    // createPartialMockWithReflection bypasses the constructor entirely and still allows stubbing the magic getter.
    private function stubQuoteLoad(int $quoteId, ?int $customerId): Quote&MockObject
    {
        $quote = $this->createPartialMockWithReflection(Quote::class, ['getCustomerId']);
        $quote->method('getCustomerId')->willReturn($customerId);

        $this->quoteResource->expects($this->once())->method('load')->with($quote, $quoteId);
        $this->quoteFactory->method('create')->willReturn($quote);

        return $quote;
    }

    public function testPrepareDataSourceReturnsUnchangedWhenNoItemsKey(): void
    {
        $dataSource = [];

        $this->assertSame($dataSource, $this->createSut()->prepareDataSource($dataSource));
    }

    public function testPrepareDataSourceWrapsNameInEditLinkForRegisteredCustomer(): void
    {
        $this->stubQuoteLoad(1, 9);
        $this->helper->method('getCustomerName')->willReturn('John Doe');
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('customer/index/edit', ['id' => 9])
            ->willReturn('http://shop/customer/index/edit/id/9');

        $dataSource = ['data' => ['items' => [['entity_id' => 1, 'customer_id' => 9]]]];

        $result = $this->createSut()->prepareDataSource($dataSource);

        $this->assertSame(
            '<a href="http://shop/customer/index/edit/id/9" target="_blank">John Doe</a>',
            $result['data']['items'][0]['customer_name']
        );
    }

    public function testPrepareDataSourceRendersPlainTextForGuestQuote(): void
    {
        $this->stubQuoteLoad(2, null);
        $this->helper->method('getCustomerName')->willReturn('Guest Buyer');
        $this->urlBuilder->expects($this->never())->method('getUrl');

        $dataSource = ['data' => ['items' => [['entity_id' => 2, 'customer_id' => null]]]];

        $result = $this->createSut()->prepareDataSource($dataSource);

        $this->assertSame('Guest Buyer', $result['data']['items'][0]['customer_name']);
    }
}
