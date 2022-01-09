<?php

declare(strict_types=1);

namespace Mageplaza\Smtp\Setup\Patch\Schema;

use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Setup\Patch\Data\DefaultCustomerGroupsAndAttributes;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;

class AddMpSmtpColumns implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CustomerResource
     */
    private $customerResource;

    /**
     * @var SubscriberResource
     */
    private $subscriberResource;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup, CustomerResource $customerResource, SubscriberResource $subscriberResource)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerResource = $customerResource;
        $this->subscriberResource = $subscriberResource;
    }

    public function apply(): void
    {
        $setup = $this->moduleDataSetup->startSetup();

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

        $this->moduleDataSetup->endSetup();
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [DefaultCustomerGroupsAndAttributes::class];
    }
}
