<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) 2017 Mageplaza (https://www.mageplaza.com/)
 * @license     http://mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Protocol
 * @package Mageplaza\Smtp\Model\Config\Source
 */
class Protocol implements ArrayInterface
{
    /**
     * to option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => 'none',
                'label' => __('None')
            ],
            [
                'value' => 'ssl',
                'label' => __('SSL')
            ],
            [
                'value' => 'tls',
                'label' => __('TLS')
            ],
        ];

        return $options;
    }
}
