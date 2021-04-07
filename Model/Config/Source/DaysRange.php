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
 * Class DaysRange
 * @package Mageplaza\Smtp\Model\Config\Source
 */
class DaysRange implements ArrayInterface
{
    const CUSTOM = 'custom';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->toArray() as $value => $label) {
            $options[] = ['label' => $label, 'value' => $value];
        }

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '30'         => __('30'),
            '60'         => __('60'),
            '90'         => __('90'),
            '365'        => __('365'),
            self::CUSTOM => __('Custom')
        ];
    }
}
