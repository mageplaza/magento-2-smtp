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

namespace Mageplaza\Smtp\Test\Unit\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\CheckoutManagement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckoutManagement::class)]
class CheckoutManagementTest extends TestCase
{
    private QuoteIdMaskFactory&MockObject $quoteIdMaskFactory;
    private CartRepositoryInterface&MockObject $cartRepository;
    private EmailMarketing&MockObject $helperEmailMarketing;

    private CheckoutManagement $sut;

    private \Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask&MockObject $quoteIdMaskResource;

    protected function setUp(): void
    {
        $this->quoteIdMaskResource = $this->createMock(\Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask::class);
        $this->quoteIdMaskFactory = $this->getMockBuilder(QuoteIdMaskFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);

        $this->sut = new CheckoutManagement(
            $this->quoteIdMaskFactory,
            $this->cartRepository,
            $this->helperEmailMarketing,
            $this->quoteIdMaskResource
        );

        // EmailMarketing::jsonDecode() resolves the Json helper through the global ObjectManager.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonDecode')->willReturnCallback(static fn ($v) => json_decode($v, true));
        $om = $this->createMock(ObjectManagerInterface::class);
        $om->method('get')->willReturn($jsonHelper);
        ObjectManager::setInstance($om);
    }

    private function enableGate(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app');
    }

    public function testUpdateOrderSkipsWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperEmailMarketing->expects($this->never())->method('getSecretKey');
        $this->quoteIdMaskFactory->expects($this->never())->method('create');
        $this->helperEmailMarketing->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->sut->updateOrder('masked123', '{"city":"NY"}', false);
    }

    public function testUpdateOrderSkipsWhenSecretKeyMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('');
        $this->helperEmailMarketing->expects($this->never())->method('getAppID');
        $this->quoteIdMaskFactory->expects($this->never())->method('create');
        $this->helperEmailMarketing->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->sut->updateOrder('masked123', '{"city":"NY"}', false);
    }

    public function testUpdateOrderSkipsWhenAppIdMissing(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('');
        $this->quoteIdMaskFactory->expects($this->never())->method('create');
        $this->helperEmailMarketing->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->sut->updateOrder('masked123', '{"city":"NY"}', false);
    }

    public function testUpdateOrderSendsAceDataWhenAllConditionsMet(): void
    {
        $this->enableGate();

        $quoteIdMask = $this->getMockBuilder(QuoteIdMask::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteIdMask->setData('quote_id', 99);
        $this->quoteIdMaskResource->expects($this->once())->method('load')
            ->with($quoteIdMask, 'masked123', 'masked_id');
        $this->quoteIdMaskFactory->method('create')->willReturn($quoteIdMask);

        $quote = new DataObject(['entity_id' => 99]);
        $this->cartRepository->expects($this->once())->method('getActive')->with(99)->willReturn($quote);

        $this->helperEmailMarketing->expects($this->once())
            ->method('getACEData')
            ->with($quote, ['city' => 'NY'], false)
            ->willReturn(['ace' => 'payload']);

        $this->helperEmailMarketing->expects($this->once())
            ->method('sendRequestWithoutWaitResponse')
            ->with(['ace' => 'payload'], EmailMarketing::CHECKOUT_URL);

        $this->sut->updateOrder('masked123', '{"city":"NY"}', false);
    }
}
