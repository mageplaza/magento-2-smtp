<?php

declare(strict_types=1);

namespace Mageplaza\Smtp\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\Patch\Data\DefaultCustomerGroupsAndAttributes;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Config\Model\ResourceModel\Config\Data\Collection as ConfigCollection;
use Magento\Framework\App\Cache\TypeListInterface;

class UpdateSmtpConfigPath implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var ConfigCollection
     */
    private $configCollection;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ConfigCollection $configCollection,
        TypeListInterface $cacheTypeList
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configCollection = $configCollection;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function apply(): void
    {
        $setup = $this->moduleDataSetup->startSetup();

        $connection = $setup->getConnection();
        $configCollection = $this->configCollection->addPathFilter('smtp/abandoned_cart');
        if ($configCollection->getSize() > 0) {
            $table = $this->configCollection->getMainTable();
            $paths = [
                'smtp/abandoned_cart/enabled' => 'email_marketing/general/enabled',
                'smtp/abandoned_cart/app_id' => 'email_marketing/general/app_id',
                'smtp/abandoned_cart/secret_key' => 'email_marketing/general/secret_key'
            ];

            foreach ($paths as $oldPath => $newPath) {
                $connection->update(
                    $table,
                    ['path' => $newPath],
                    ['path = ?' => $oldPath]
                );
            }
            $this->cacheTypeList->cleanType('config');
        }

        $this->moduleDataSetup->endSetup();
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
