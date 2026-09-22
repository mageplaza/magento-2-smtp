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

namespace Mageplaza\Smtp\Model;

/**
 * Class AbandonedCartToken
 * @package Mageplaza\Smtp\Model
 */
class AbandonedCartToken
{
    const LENGTH = 32;

    /**
     * @param mixed $token
     *
     * @return bool
     */
    public static function isValid($token)
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9]{' . self::LENGTH . '}\z/', $token) === 1;
    }
}
