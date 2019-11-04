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

        $setup->endSetup();
    }
}
