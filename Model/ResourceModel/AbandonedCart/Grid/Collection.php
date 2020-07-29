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

namespace Mageplaza\Smtp\Model\ResourceModel\AbandonedCart\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Class Collection
 * @package Mageplaza\Smtp\Model\ResourceModel\AbandonedCart\Grid
 */
class Collection extends SearchResult
{
    /**
     * @return $this|SearchResult|void
     */
    public function _initSelect()
    {
        $quoteTable = $this->getResource()->getTable('quote');
        $quoteField =  [
            'store_id',
            'customer_id',
            'customer_group_id',
            'customer_email',
            'customer_firstname',
            'customer_lastname'
        ];
        $this->getSelect()
            ->from(
                ['main_table' => $this->getMainTable()]
            )
            ->join(
                ['quote' => $quoteTable],
                'quote.entity_id = main_table.quote_id',
                $quoteField
            );

        $this->addFilterToMap('created_at', 'main_table.created_at');

        foreach ($quoteField as $column) {
            $this->addFilterToMap($column, 'quote.' . $column);
        }

        return $this;
    }
}
