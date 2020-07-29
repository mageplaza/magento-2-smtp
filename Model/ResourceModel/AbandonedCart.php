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

namespace Mageplaza\Smtp\Model\ResourceModel;

use Exception;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Model\ResourceModel
 */
class AbandonedCart extends AbstractDb
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init('mageplaza_smtp_abandonedcart', 'id');
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    public function insertAbandonedCart($data)
    {
        $this->getConnection()->beginTransaction();
        try {
            $this->getConnection()->insertMultiple($this->getMainTable(), $data);
            $this->getConnection()->commit();
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }
}
