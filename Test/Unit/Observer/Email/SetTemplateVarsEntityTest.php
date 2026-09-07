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

namespace Mageplaza\Smtp\Test\Unit\Observer\Email;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Mageplaza\Smtp\Observer\Email\SetTemplateVarsEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// SMTP-4: the module cannot flip sales_order.email_sent on Resend without knowing which
// entity a failed email belonged to. Core dispatches the *_set_template_vars_before events
// with a transportObject DataObject carrying the entity (order/invoice/shipment/creditmemo);
// this observer reads it and stashes {entity_type, entity_id} in the registry so
// Transport::emailLog() can persist it onto the mageplaza_smtp_log row.
#[CoversClass(SetTemplateVarsEntity::class)]
class SetTemplateVarsEntityTest extends TestCase
{
    /**
     * @var Registry&MockObject
     */
    private Registry&MockObject $registry;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface&MockObject $logger;

    /**
     * @var SetTemplateVarsEntity
     */
    private SetTemplateVarsEntity $subject;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->logger   = $this->createMock(LoggerInterface::class);
        $this->subject  = new SetTemplateVarsEntity($this->registry, $this->logger);
    }

    // Core dispatches 'transportObject' as a literal camelCase array key (see
    // OrderSender::prepareTemplate() etc.) -- unlike getDataObject()/'data_object', this key
    // is NOT underscored, so it must be read back with getData('transportObject'), not the
    // magic getTransportObject() (which would look up the wrong key 'transport_object').
    private function observerWithTransportObject(?DataObject $transportObject): Observer&MockObject
    {
        $event    = new Event($transportObject !== null ? ['transportObject' => $transportObject] : []);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function makeEntity(string $class, int $id): MockObject
    {
        $entity = $this->createMock($class);
        $entity->method('getId')->willReturn($id);

        return $entity;
    }

    public function testExecuteRegistersOrderEntityFromOrderEvent(): void
    {
        $order = $this->makeEntity(Order::class, 42);
        $transportObject = new DataObject(['order' => $order]);

        $this->registry->method('registry')->with('mp_smtp_email_entity')->willReturn(null);
        $this->registry->expects($this->once())->method('register')
            ->with('mp_smtp_email_entity', ['entity_type' => 'order', 'entity_id' => 42]);

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    public function testExecuteRegistersInvoiceEntityFromInvoiceEvent(): void
    {
        $order   = $this->makeEntity(Order::class, 42);
        $invoice = $this->makeEntity(Invoice::class, 7);
        $transportObject = new DataObject(['order' => $order, 'invoice' => $invoice]);

        $this->registry->method('registry')->willReturn(null);
        $this->registry->expects($this->once())->method('register')
            ->with('mp_smtp_email_entity', ['entity_type' => 'invoice', 'entity_id' => 7]);

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    public function testExecuteRegistersShipmentEntityFromShipmentEvent(): void
    {
        $order    = $this->makeEntity(Order::class, 42);
        $shipment = $this->makeEntity(Shipment::class, 9);
        $transportObject = new DataObject(['order' => $order, 'shipment' => $shipment]);

        $this->registry->method('registry')->willReturn(null);
        $this->registry->expects($this->once())->method('register')
            ->with('mp_smtp_email_entity', ['entity_type' => 'shipment', 'entity_id' => 9]);

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    public function testExecuteRegistersCreditmemoEntityFromCreditmemoEvent(): void
    {
        $order       = $this->makeEntity(Order::class, 42);
        $creditmemo  = $this->makeEntity(Creditmemo::class, 3);
        $transportObject = new DataObject(['order' => $order, 'creditmemo' => $creditmemo]);

        $this->registry->method('registry')->willReturn(null);
        $this->registry->expects($this->once())->method('register')
            ->with('mp_smtp_email_entity', ['entity_type' => 'creditmemo', 'entity_id' => 3]);

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    public function testExecuteUnregistersStaleValueBeforeRegisteringNewOne(): void
    {
        $order = $this->makeEntity(Order::class, 42);
        $transportObject = new DataObject(['order' => $order]);

        $this->registry->method('registry')->willReturn(['entity_type' => 'order', 'entity_id' => 1]);

        $calls = [];
        $this->registry->method('unregister')->willReturnCallback(
            static function (string $key) use (&$calls): void {
                $calls[] = $key;
            }
        );

        $this->subject->execute($this->observerWithTransportObject($transportObject));

        $this->assertSame(['mp_smtp_email_entity'], $calls);
    }

    public function testExecuteDoesNothingWhenTransportObjectMissing(): void
    {
        $this->registry->expects($this->never())->method('register');

        $this->subject->execute($this->observerWithTransportObject(null));
    }

    public function testExecuteDoesNothingWhenTransportObjectHasNoKnownEntity(): void
    {
        $transportObject = new DataObject(['billing' => 'irrelevant']);

        $this->registry->expects($this->never())->method('register');

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    public function testExecuteSkipsEntityWithoutId(): void
    {
        $order = $this->makeEntity(Order::class, 0);
        $order->method('getId')->willReturn(null);
        $transportObject = new DataObject(['order' => $order]);

        $this->registry->expects($this->never())->method('register');

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }

    // The observer sits in core Sales' email-sending flow -- it must never let a problem
    // reading the transport object (or the registry) escape and break the send.
    public function testExecuteSwallowsThrowableFromTransportObject(): void
    {
        $transportObject = $this->createMock(DataObject::class);
        $transportObject->method('getData')->willThrowException(new \RuntimeException('boom'));

        $this->registry->expects($this->never())->method('register');
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('boom'));

        $this->subject->execute($this->observerWithTransportObject($transportObject));
    }
}
