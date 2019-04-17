<?php


namespace Mageplaza\Smtp\Setup;


use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $setup->getConnection()->dropTable($setup->getConnection()->getTableName('mageplaza_smtp_log'));
        $setup->endSetup();
    }


}