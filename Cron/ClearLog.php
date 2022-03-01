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

namespace Mageplaza\Smtp\Cron;

use Exception;
use Mageplaza\Smtp\Helper\ClearLog as Helper;

/**
 * Class ClearLog
 * @package Mageplaza\Smtp\Cron
 */
class ClearLog
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * ClearLog constructor.
     *
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    )
    {
        $this->helper = $helper;
    }

    /**
     * Clean Email Log after X day(s)
     *
     * @return $this
     */
    public function execute()
    {
        $this->helper->execute();
        return $this;
    }
}
