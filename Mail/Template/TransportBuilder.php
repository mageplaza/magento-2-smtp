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

namespace Mageplaza\Smtp\Mail\Template;

/**
 * Class TransportBuilder
 * @package Mageplaza\Smtp\Mail\Template
 */
class TransportBuilder
{
    /**
     * @var \Magento\Framework\Registry $registry
     */

    protected $registry;

    /**
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    )
    {
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Framework\Mail\Template\TransportBuilder $subject
     * @param $templateOptions
     * @return array
     */
    public function beforeSetTemplateOptions(
        \Magento\Framework\Mail\Template\TransportBuilder $subject,
        $templateOptions
    ) {
        if (array_key_exists('store', $templateOptions)) {
            $this->registry->register('store_id', $templateOptions['store']);
        } else {
            $this->registry->register('store_id', null);
        }
        return [$templateOptions];
    }
}
