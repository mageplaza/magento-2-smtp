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

namespace Mageplaza\Smtp\Observer\Email;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Captures which sales entity (order/invoice/shipment/creditmemo) an outgoing email belongs
 * to, so Transport::emailLog() can persist it onto the mageplaza_smtp_log row. Without this,
 * a failed order-confirmation email has no way back to the order, and Resend can never flip
 * sales_order.email_sent.
 *
 * Registered on the four core "*_set_template_vars_before" events (see etc/events.xml),
 * each of which carries a transportObject DataObject holding the entity.
 *
 * Class SetTemplateVarsEntity
 * @package Mageplaza\Smtp\Observer\Email
 */
class SetTemplateVarsEntity implements ObserverInterface
{
    public const REGISTRY_KEY = 'mp_smtp_email_entity';

    /**
     * Entity keys on the transportObject, in priority order. An invoice/shipment/creditmemo
     * email also carries 'order' on the same transportObject, so order must be checked last.
     */
    private const ENTITY_KEYS = ['invoice', 'shipment', 'creditmemo', 'order'];

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SetTemplateVarsEntity constructor.
     *
     * @param Registry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(Registry $registry, LoggerInterface $logger)
    {
        $this->registry = $registry;
        $this->logger   = $logger;
    }

    /**
     * Capture the sales entity from the dispatched event and store it in the registry.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            // 'transportObject' is the literal (camelCase) array key core dispatches with --
            // not underscored, so it must be read via getData(), not the magic getter.
            $transportObject = $observer->getEvent()->getData('transportObject');
            if (!$transportObject) {
                return;
            }

            $entityType = null;
            $entity     = null;
            foreach (self::ENTITY_KEYS as $key) {
                $candidate = $transportObject->getData($key);
                if ($candidate) {
                    $entityType = $key;
                    $entity     = $candidate;
                    break;
                }
            }

            if (!$entity || !$entity->getId()) {
                return;
            }

            if ($this->registry->registry(self::REGISTRY_KEY)) {
                $this->registry->unregister(self::REGISTRY_KEY);
            }
            $this->registry->register(self::REGISTRY_KEY, [
                'entity_type' => $entityType,
                'entity_id'   => (int) $entity->getId(),
            ]);
        } catch (Throwable $e) {
            // This observer sits inside core Sales' email-sending flow -- it must never
            // break the actual send. Losing the entity link just means the log row (and a
            // later Resend) can't flag the order/invoice/shipment/creditmemo as emailed.
            $this->logger->error('Mageplaza_Smtp: failed to capture email entity: ' . $e->getMessage());
        }
    }
}
