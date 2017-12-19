<?php

namespace Mageplaza\Smtp\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * Class UpgradeSchema
 * @package Mageplaza\SeoRule\Setup
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

	/**
	 * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
	 * @param \Magento\Framework\Setup\ModuleContextInterface $context
	 */
	public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$setup->startSetup();

		if( version_compare($context->getVersion(), '1.1.0', '<' )){
            $setup->getConnection()
                ->addColumn($setup->getTable('mageplaza_smtp_log'), 'from', [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'length' => 255,
                    'comment' => 'Sender'
                ]
            );
            $setup->getConnection()
                ->addColumn($setup->getTable('mageplaza_smtp_log'), 'to', [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'length' => 255,
                    'comment' => 'Recipient'
                ]
            );
            $setup->getConnection()
                ->addColumn($setup->getTable('mageplaza_smtp_log'), 'cc', [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'length' => 255,
                    'comment' => 'Cc'
                ]
            );
            $setup->getConnection()
                ->addColumn($setup->getTable('mageplaza_smtp_log'), 'bcc', [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'length' => 255,
                    'comment' => 'Bcc'
                ]
            );
		}

		$setup->endSetup();
	}



}