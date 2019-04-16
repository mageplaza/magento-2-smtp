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

namespace Mageplaza\Smtp\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\Smtp\Model\ResourceModel\Log;

/**
 * Class Collection
 * @package Mageplaza\Smtp\Model\ResourceModel\Log
 */
class Collection extends AbstractCollection
{
    /**
     * @type string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Mageplaza\Smtp\Model\Log::class, Log::class);
    }

    /**
     * Truncate table emails log
     *
     * @return void
     */
    public function clearLog()
    {
        $this->getConnection()->truncateTable($this->getMainTable());
    }
}
