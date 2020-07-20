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

namespace Mageplaza\Smtp\Cron;

use Exception;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Model\ResourceModel\AbandonedCart\CollectionFactory;
use Psr\Log\LoggerInterface;
use Mageplaza\Smtp\Helper\Data;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Zend_Db_Expr;
use Mageplaza\Smtp\Model\ResourceModel\AbandonedCart as ResourceAbandonedCart;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Cron
 */
class AbandonedCart
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionFactory
     */
    protected $abandonedCartCollectionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var QuoteCollectionFactory
     */
    protected $quoteCollectionFactory;

    /**
     * @var ResourceAbandonedCart
     */
    protected $resourceAbandonedCart;

    /**
     * @var Random
     */
    protected $random;

    /**
     * AbandonedCart constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $abandonedCartCollectionFactory
     * @param LoggerInterface $logger
     * @param Data $helperData
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param Random $random
     * @param ResourceAbandonedCart $resourceAbandonedCart
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CollectionFactory $abandonedCartCollectionFactory,
        LoggerInterface $logger,
        Data $helperData,
        QuoteCollectionFactory $quoteCollectionFactory,
        Random $random,
        ResourceAbandonedCart $resourceAbandonedCart
    ) {
        $this->logger                         = $logger;
        $this->storeManager                   = $storeManager;
        $this->abandonedCartCollectionFactory = $abandonedCartCollectionFactory;
        $this->helperData                     = $helperData;
        $this->quoteCollectionFactory         = $quoteCollectionFactory;
        $this->random                         = $random;
        $this->resourceAbandonedCart          = $resourceAbandonedCart;
    }

    /**
     * @return void
     */
    public function execute()
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->helperData->isEnableAbandonedCart($store->getId())) {
                $abandonedCartData = [];
                try {
                    $measure         = $this->helperData->getAbandonedCartConfig('measure', $store->getId());
                    $quoteCollection = $this->quoteCollectionFactory->create()
                        ->addFieldToFilter('items_count', ['neq' => '0'])
                        ->addFieldToFilter('is_active', 1)
                        ->addFieldToFilter('store_id', $store->getId())
                        ->addFieldToFilter('customer_email', ['neq' => null])
                        ->addFieldToFilter(
                            'updated_at',
                            [
                                'lteq' =>  new Zend_Db_Expr(' NOW() - INTERVAL ' . $measure . ' MINUTE')
                            ]
                        )
                        ->addFieldToFilter(
                            'updated_at',
                            [
                                'gt' =>  new Zend_Db_Expr(' NOW() - INTERVAL 1 DAY')
                            ]
                        );
                    $table = $quoteCollection->getResource()->getTable('mageplaza_smtp_abandonedcart');
                    $quoteCollection->getSelect()
                        ->where(
                            new Zend_Db_Expr(
                                sprintf(
                                    'NOT EXISTS(select `quote_id` FROM `%s` WHERE `quote_id` = main_table.`entity_id`)',
                                    $table
                                )
                            )
                        );

                    if ($quoteCollection->getSize() > 0) {
                        foreach ($quoteCollection->getItems() as $quote) {
                            $abandonedCartData[] = [
                                'quote_id' => $quote->getId(),
                                'token'    => $this->random->getUniqueHash(),
                                'status'   => 0
                            ];
                        }

                        $this->resourceAbandonedCart->insertAbandonedCart($abandonedCartData);
                    }
                } catch (Exception $e) {
                    $this->logger->critical($e);
                }
            }
        }
    }
}
