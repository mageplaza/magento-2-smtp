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

namespace Mageplaza\Smtp\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Zend_Db_Exception;

/**
 * Class UpgradeSchema
 * @package Mageplaza\Smtp\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var QuoteResource
     */
    protected $quoteResource;

    /**
     * @var OrderResource
     */
    protected $orderResource;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * @var SubscriberResource
     */
    protected $subscriberResource;

    /**
     * UpgradeSchema constructor.
     *
     * @param QuoteResource $quoteResource
     * @param OrderResource $orderResource
     * @param CustomerResource $customerResource
     * @param SubscriberResource $subscriberResource
     */
    public function __construct(
        QuoteResource $quoteResource,
        OrderResource $orderResource,
        CustomerResource $customerResource,
        SubscriberResource $subscriberResource
    ) {
        $this->quoteResource      = $quoteResource;
        $this->orderResource      = $orderResource;
        $this->customerResource   = $customerResource;
        $this->subscriberResource = $subscriberResource;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @throws Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $connection->addColumn($setup->getTable('mageplaza_smtp_log'), 'from', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Sender'
            ]);
            $connection->addColumn($setup->getTable('mageplaza_smtp_log'), 'to', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Recipient'
            ]);
            $connection->addColumn($setup->getTable('mageplaza_smtp_log'), 'cc', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Cc'
            ]);
            $connection->addColumn($setup->getTable('mageplaza_smtp_log'), 'bcc', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Bcc'
            ]);
        }

        if (version_compare($context->getVersion(), '1.1.1', '<')) {
            $connection->changeColumn($setup->getTable('mageplaza_smtp_log'), 'from', 'sender', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Sender'
            ]);
            $connection->changeColumn($setup->getTable('mageplaza_smtp_log'), 'to', 'recipient', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'Recipient'
            ]);
        }

        if (!$setup->tableExists('mageplaza_smtp_abandonedcart')) {
            $table = $setup->getConnection()
                ->newTable($setup->getTable('mageplaza_smtp_abandonedcart'))
                ->addColumn('id', Table::TYPE_INTEGER, null, [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true
                ], 'Log Id')
                ->addColumn('log_ids', Table::TYPE_TEXT, 255, [], 'Log Ids')
                ->addColumn('token', Table::TYPE_TEXT, 255, [], 'Token')
                ->addColumn(
                    'quote_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'unsigned' => true,
                        'nullable' => false,
                        'default'  => '0'
                    ],
                    'Quote Id'
                )
                ->addColumn('status', Table::TYPE_SMALLINT, 1, ['nullable' => false], 'Status')
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['default' => Table::TIMESTAMP_INIT],
                    'Created At'
                )
                ->addForeignKey(
                    $setup->getFkName(
                        'mageplaza_smtp_abandonedcart',
                        'quote_id',
                        'quote',
                        'entity_id'
                    ),
                    'quote_id',
                    $setup->getTable('quote'),
                    'entity_id',
                    Table::ACTION_CASCADE
                )
                ->setComment('SMTP Abandoned Cart');

            $setup->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.2.1', '<')) {
            $quoteConnection = $this->quoteResource->getConnection();
            $quoteConnection->addColumn($setup->getTable('quote'), 'mp_smtp_ace_token', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => 255,
                'comment'  => 'ACE Token'
            ]);
            $quoteConnection->addColumn($setup->getTable('quote'), 'mp_smtp_ace_sent', [
                'type'     => Table::TYPE_SMALLINT,
                'nullable' => true,
                'length'   => null,
                'default'  => 0,
                'comment'  => 'ACE Sent'
            ]);
            $quoteConnection->addColumn($setup->getTable('quote'), 'mp_smtp_ace_log_ids', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => '64k',
                'comment'  => 'ACE Log Ids'
            ]);
            $quoteConnection->addColumn($setup->getTable('quote'), 'mp_smtp_ace_log_data', [
                'type'     => Table::TYPE_TEXT,
                'nullable' => true,
                'length'   => '64k',
                'comment'  => 'ACE Log Data'
            ]);
        }

        if (version_compare($context->getVersion(), '1.2.2', '<')) {
            $salesOrderConnection = $this->orderResource->getConnection();
            $salesOrderConnection->addColumn($setup->getTable('sales_order'), 'mp_smtp_email_marketing_synced', [
                'type'     => Table::TYPE_SMALLINT,
                'nullable' => true,
                'length'   => null,
                'default'  => 0,
                'comment'  => 'Mp SMTP Email Marketing synced'
            ]);
        }

        if (version_compare($context->getVersion(), '1.2.4', '<')) {
            $column = [
                'type'     => Table::TYPE_SMALLINT,
                'nullable' => true,
                'length'   => null,
                'default'  => 0,
                'comment'  => 'Mp SMTP Email Marketing synced'
            ];

            $customerConnection = $this->customerResource->getConnection();
            $customerConnection->addColumn(
                $setup->getTable('customer_entity'),
                'mp_smtp_email_marketing_synced',
                $column
            );

            $subscriberConnection = $this->subscriberResource->getConnection();
            $subscriberConnection->addColumn(
                $setup->getTable('newsletter_subscriber'),
                'mp_smtp_email_marketing_synced',
                $column
            );
        }

        if (version_compare($context->getVersion(), '1.2.5', '<')) {
            $salesOrderConnection = $this->orderResource->getConnection();
            $salesOrderConnection->addColumn($setup->getTable('sales_order'), 'mp_smtp_email_marketing_order_created', [
                'type'     => Table::TYPE_SMALLINT,
                'nullable' => true,
                'length'   => null,
                'default'  => 0,
                'comment'  => 'Mp SMTP Email Marketing order created'
            ]);
        }

        $setup->endSetup();
    }
}
