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

namespace Mageplaza\Smtp\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as CreditmemoResource;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Magento\Sales\Model\ResourceModel\Order\Shipment as ShipmentResource;

/**
 * Flips the "confirmation email was sent" flag (send_email/email_sent) on a sales entity
 * without a full save() -- saveAttribute() only touches those two columns, so it can't
 * retrigger the entity's own save observers/indexers.
 *
 * Used by Log::resendEmail() (SMTP-4): once a manual Resend succeeds, the order/invoice/
 * shipment/creditmemo the original failed email belonged to needs this flag set so admin
 * no longer sees the "email not sent" banner.
 *
 * Class EmailSentFlagUpdater
 * @package Mageplaza\Smtp\Model
 */
class EmailSentFlagUpdater
{
    private const SEND_EMAIL_ATTRIBUTES = ['send_email', 'email_sent'];

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderResource
     */
    protected $orderResource;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var InvoiceResource
     */
    protected $invoiceResource;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentRepository;

    /**
     * @var ShipmentResource
     */
    protected $shipmentResource;

    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var CreditmemoResource
     */
    protected $creditmemoResource;

    /**
     * EmailSentFlagUpdater constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderResource $orderResource
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param InvoiceResource $invoiceResource
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShipmentResource $shipmentResource
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoResource $creditmemoResource
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderResource $orderResource,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceResource $invoiceResource,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentResource $shipmentResource,
        CreditmemoRepositoryInterface $creditmemoRepository,
        CreditmemoResource $creditmemoResource
    ) {
        $this->orderRepository      = $orderRepository;
        $this->orderResource        = $orderResource;
        $this->invoiceRepository    = $invoiceRepository;
        $this->invoiceResource      = $invoiceResource;
        $this->shipmentRepository   = $shipmentRepository;
        $this->shipmentResource     = $shipmentResource;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoResource   = $creditmemoResource;
    }

    /**
     * Load the given entity and flag it as emailed. No-ops silently (never throws) when the
     * entity type is not one of order/invoice/shipment/creditmemo, when either argument is
     * empty, or when the entity can't be loaded -- the caller (a Resend that already
     * succeeded) must not be turned into a failure by this.
     *
     * @param string|null $entityType
     * @param int|string|null $entityId
     */
    public function updateEmailSent(?string $entityType, $entityId): void
    {
        if (!$entityType || !$entityId) {
            return;
        }

        [$repository, $resource] = $this->resolve($entityType);
        if (!$repository || !$resource) {
            return;
        }

        try {
            $entity = $repository->get($entityId);
        } catch (NoSuchEntityException $e) {
            return;
        }

        // saveAttribute() only persists whatever value is already set on $entity for the
        // given attributes (see Magento\Sales\Model\ResourceModel\Attribute::saveAttribute()) --
        // it does not flip anything itself. send_email is already true from the original send
        // attempt; email_sent is the one that needs setting here, same as core's own
        // OrderSender/InvoiceSender/... do right before their own saveAttribute() call.
        $entity->setData('email_sent', true);
        $resource->saveAttribute($entity, self::SEND_EMAIL_ATTRIBUTES);
    }

    /**
     * @param string $entityType
     *
     * @return array{0: ?object, 1: ?object}
     */
    private function resolve(string $entityType): array
    {
        switch ($entityType) {
            case 'order':
                return [$this->orderRepository, $this->orderResource];
            case 'invoice':
                return [$this->invoiceRepository, $this->invoiceResource];
            case 'shipment':
                return [$this->shipmentRepository, $this->shipmentResource];
            case 'creditmemo':
                return [$this->creditmemoRepository, $this->creditmemoResource];
            default:
                return [null, null];
        }
    }
}
