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
 * Class Authentication
 * @package Mageplaza\Smtp\Model\Config\Source
 */
class Authentication implements ArrayInterface
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
                'value' => '',
                'label' => __('NONE')
            ],
            [
                'value' => 'plain',
                'label' => __('PLAIN')
            ],
            [
                'value' => 'login',
                'label' => __('LOGIN')
            ],
            [
                'value' => 'crammd5',
                'label' => __('CRAM-MD5')
            ],
        ];

        return $options;
    }
}
