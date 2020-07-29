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

namespace Mageplaza\Smtp\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class AbandonedCartStatus
 * @package Mageplaza\Smtp\Model\Source
 */
class AbandonedCartStatus implements OptionSourceInterface
{
    const SENT          = 1;
    const WAIT_FOR_SEND = 0;

    /**
     * @return array
     */
    public static function getOptionArray()
    {
        return [
            self::SENT   => __('Sent'),
            self::WAIT_FOR_SEND => __('Wait for send')
        ];
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];

        foreach (self::getOptionArray() as $index => $value) {
            $result[] = ['value' => $index, 'label' => $value];
        }

        return $result;
    }
}
