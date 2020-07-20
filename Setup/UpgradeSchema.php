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

/**
 * Class UpgradeSchema
 * @package Mageplaza\Smtp\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @throws \Zend_Db_Exception
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

        $setup->endSetup();
    }
}
