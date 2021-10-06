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

namespace Mageplaza\Smtp\Observer\Customer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\PageCache\Model\Cache\Type;
use Mageplaza\Smtp\Block\Script;
use Zend_Cache;

/**
 * Class LoginSuccess
 * @package Mageplaza\Smtp\Observer\Customer
 */
class LoginSuccess implements ObserverInterface
{
    /**
     * @var Type
     */
    protected $fullPageCache;

    /**
     * LoginSuccess constructor.
     *
     * @param Type $fullPageCache
     */
    public function __construct(
        Type $fullPageCache
    ) {
        $this->fullPageCache = $fullPageCache;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->fullPageCache->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, [Script::CACHE_TAG]);
    }
}
