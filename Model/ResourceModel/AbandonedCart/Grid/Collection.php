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
        parent::_initSelect();

        $this->getSelect()
            ->where('main_table.is_active = ?', 1)
            ->where('main_table.customer_email != ?', null)
            ->where('main_table.items_count != ?', 0);

        return $this;
    }
}
