<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Smtp\Ui\Component\Listing\Column;

use Magento\Customer\Model\Config\Source\Group;

/**
 * Class CustomerGroup
 * @package Mageplaza\Smtp\Ui\Component\Listing\Column
 */
class CustomerGroup extends Group
{
    /**
     * @return array|void
     */
    public function toOptionArray()
    {
        $options   = parent::toOptionArray();
        $options[] = ['value' => '0', 'label' => __('NOT LOGGED IN')];

        return $options;
    }
}
