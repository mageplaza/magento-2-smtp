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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Helper;

use Mageplaza\Core\Helper\AbstractData;

/**
 * Class Data
 * @package Mageplaza\Smtp\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'smtp';

    const CONFIG_GROUP_SMTP = 'configuration_option';
    const DEVELOP_GROUP_SMTP = 'developer';

    /**
     * @param $group
     * @param $code
     * @param null $storeId
     * @return mixed
     */
    public function getConfig($group, $code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getConfigValue(static::CONFIG_MODULE_PATH . '/' . $group . $code, $storeId);
    }
}
