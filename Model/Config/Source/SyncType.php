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

namespace Mageplaza\Smtp\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class SyncType
 * @package Mageplaza\Smtp\Model\Config\Source
 */
class SyncType implements ArrayInterface
{
    const ALL         = 'all';
    const CUSTOMERS   = 1;
    const ORDERS      = 2;
    const SUBSCRIBERS = 3;

    /**
     * to option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::CUSTOMERS,
                'label' => __('Customers')
            ],
            [
                'value' => self::ORDERS,
                'label' => __('Orders')
            ],
            [
                'value' => self::SUBSCRIBERS,
                'label' => __('Subscribers')
            ],
            [
                'value' => self::ALL,
                'label' => __('Everything')
            ]
        ];

        return $options;
    }
}
