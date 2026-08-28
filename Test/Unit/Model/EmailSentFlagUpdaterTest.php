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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as CreditmemoResource;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;
use Mageplaza\Smtp\Model\EmailSentFlagUpdater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// SMTP-4: Log::resendEmail() must flip send_email/email_sent on the entity a failed email
// belonged to, without touching anything else on it (no ->save(), no re-triggering other
// observers/indexers) -- hence saveAttribute() rather than loading + full save().
#[CoversClass(EmailSentFlagUpdater::class)]
class EmailSentFlagUpdaterTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepository;
    private OrderResource&MockObject $orderResource;
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;
    private InvoiceResource&MockObject $invoiceResource;
    private ShipmentRepositoryInterface&MockObject $shipmentRepository;
    private ShipmentResource&MockObject $shipmentResource;
    private CreditmemoRepositoryInterface&MockObject $creditmemoRepository;
    private CreditmemoResource&MockObject $creditmemoResource;
    private EmailSentFlagUpdater $subject;

    protected function setUp(): void
    {
        $this->orderRepository      = $this->createMock(OrderRepositoryInterface::class);
        $this->orderResource        = $this->createMock(OrderResource::class);
        $this->invoiceRepository    = $this->createMock(InvoiceRepositoryInterface::class);
        $this->invoiceResource      = $this->createMock(InvoiceResource::class);
        $this->shipmentRepository   = $this->createMock(ShipmentRepositoryInterface::class);
        $this->shipmentResource     = $this->createMock(ShipmentResource::class);
        $this->creditmemoRepository = $this->createMock(CreditmemoRepositoryInterface::class);
        $this->creditmemoResource   = $this->createMock(CreditmemoResource::class);

        $this->subject = new EmailSentFlagUpdater(
            $this->orderRepository,
            $this->orderResource,
            $this->invoiceRepository,
            $this->invoiceResource,
            $this->shipmentRepository,
            $this->shipmentResource,
            $this->creditmemoRepository,
            $this->creditmemoResource
        );
    }

    public function testUpdateEmailSentFlagsOrderWhenFound(): void
    {
        $order = $this->createMock(Order::class);
        $this->orderRepository->method('get')->with(55)->willReturn($order);

        $this->orderResource->expects($this->once())->method('saveAttribute')
            ->with($order, ['send_email', 'email_sent']);

        $this->subject->updateEmailSent('order', 55);
    }

    public function testUpdateEmailSentFlagsInvoiceWhenFound(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $this->invoiceRepository->method('get')->with(7)->willReturn($invoice);

        $this->invoiceResource->expects($this->once())->method('saveAttribute')
            ->with($invoice, ['send_email', 'email_sent']);

        $this->subject->updateEmailSent('invoice', 7);
    }

    public function testUpdateEmailSentFlagsShipmentWhenFound(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $this->shipmentRepository->method('get')->with(9)->willReturn($shipment);

        $this->shipmentResource->expects($this->once())->method('saveAttribute')
            ->with($shipment, ['send_email', 'email_sent']);

        $this->subject->updateEmailSent('shipment', 9);
    }

    public function testUpdateEmailSentFlagsCreditmemoWhenFound(): void
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $this->creditmemoRepository->method('get')->with(3)->willReturn($creditmemo);

        $this->creditmemoResource->expects($this->once())->method('saveAttribute')
            ->with($creditmemo, ['send_email', 'email_sent']);

        $this->subject->updateEmailSent('creditmemo', 3);
    }

    public function testUpdateEmailSentSkipsWhenEntityTypeIsUnsupported(): void
    {
        $this->orderRepository->expects($this->never())->method('get');
        $this->orderResource->expects($this->never())->method('saveAttribute');

        $this->subject->updateEmailSent('quote', 1);
    }

    public function testUpdateEmailSentSkipsWhenEntityTypeIsNull(): void
    {
        $this->orderRepository->expects($this->never())->method('get');

        $this->subject->updateEmailSent(null, 1);
    }

    public function testUpdateEmailSentSkipsWhenEntityIdIsEmpty(): void
    {
        $this->orderRepository->expects($this->never())->method('get');

        $this->subject->updateEmailSent('order', null);
    }

    public function testUpdateEmailSentSkipsWhenEntityNotFound(): void
    {
        $this->orderRepository->method('get')->with(999)
            ->willThrowException(new NoSuchEntityException(new Phrase('not found')));

        $this->orderResource->expects($this->never())->method('saveAttribute');

        $this->subject->updateEmailSent('order', 999);
    }
}
