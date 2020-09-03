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

namespace Mageplaza\Smtp\Plugin\Shopcart\Abandoned;

use Magento\Framework\Data\Collection;
use Magento\Reports\Block\Adminhtml\Shopcart\Abandoned\Grid as AbandonedGrid;
use Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Grid\Renderer\Action;

/***
 * Class Grid
 * @package Mageplaza\Smtp\Plugin\Shopcart\Abandoned
 */
class Grid
{

    /**
     * @param AbandonedGrid $subject
     *
     * @throws \Exception
     */
    public function beforeSortColumnsByOrder(AbandonedGrid $subject)
    {
        $subject->addColumn(
            'remote_ip1',
            [
                'header'           => __('Action'),
                'index'            => 'action',
                'sortable'         => false,
                'header_css_class' => 'col-action',
                'column_css_class' => 'col-action',
                'renderer'         => Action::class
            ]
        );
    }

    /**
     * @param AbandonedGrid $subject
     * @param Collection $collection
     *
     * @return array
     */
    public function beforeSetCollection(AbandonedGrid $subject, $collection)
    {
        if ($collection && $collection instanceof Collection) {
            $collection->getSelect()->columns(
                ['quote_id' => 'main_table.entity_id']
            );
        }

        return [$collection];
    }
}
