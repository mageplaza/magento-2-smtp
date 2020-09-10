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

namespace Mageplaza\Smtp\Block;

use Magento\Catalog\Block\Product\Context;
use Magento\Framework\View\Element\Template;
use Mageplaza\Smtp\Helper\AbandonedCart as HelperAbandonedCart;

/**
 * Class Script
 * @package Mageplaza\Smtp\Block
 */
class Script extends Template
{
    /**
     * @var HelperAbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * Script constructor.
     *
     * @param Context $context
     * @param HelperAbandonedCart $helperAbandonedCart
     * @param array $data
     */
    public function __construct(
        Context $context,
        HelperAbandonedCart $helperAbandonedCart,
        array $data = []
    ) {
        $this->helperAbandonedCart         = $helperAbandonedCart;
        parent::__construct($context, $data);
    }

    /**
     * @return HelperAbandonedCart
     */
    public function getHelperAbandonedCart()
    {
        return $this->helperAbandonedCart;
    }
}
