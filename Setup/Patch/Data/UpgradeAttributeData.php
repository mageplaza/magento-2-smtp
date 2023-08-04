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

namespace Mageplaza\Smtp\Setup\Patch\Data;

use Magento\Config\Model\ResourceModel\Config\Data\Collection;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Validator\ValidateException;

/**
 * Class UpgradeAttributeData
 * @package Mageplaza\Smtp\Setup\Patch\Data
 */
class UpgradeAttributeData implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var AttributeSetFactory
     */
    protected $attributeSetFactory;

    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var Collection
     */
    protected $configCollection;

    /**
     * @var TypeListInterface
     */
    protected $_cacheTypeList;

    /**
     * UpgradeData constructor.
     *
     * @param AttributeSetFactory $attributeSetFactory
     * @param CustomerSetupFactory $customerSetupFactory
     * @param Collection $configCollection
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        AttributeSetFactory $attributeSetFactory,
        CustomerSetupFactory $customerSetupFactory,
        Collection $configCollection,
        TypeListInterface $cacheTypeList
    ) {
        $this->moduleDataSetup      = $moduleDataSetup;
        $this->attributeSetFactory  = $attributeSetFactory;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->configCollection     = $configCollection;
        $this->_cacheTypeList       = $cacheTypeList;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LocalizedException
     * @throws ValidateException
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;

        $setup->startSetup();

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(Customer::ENTITY, 'mp_smtp_is_synced', [
            'type' => 'int',
            'label' => 'Mp SMTP is synced',
            'input' => 'hidden',
            'required' => false,
            'visible' => false,
            'user_defined' => false,
            'sort_order' => 90,
            'position' => 90,
            'system' => 0,
            'is_used_in_grid' => false,
        ]);

        $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'mp_smtp_is_synced')
            ->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer']
            ])
            ->save();

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
            $this->_cacheTypeList->cleanType('config');
        }
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @return array
     */
    public function revert()
    {
        return [];
    }
}
