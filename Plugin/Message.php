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

namespace Mageplaza\Smtp\Plugin;

use Magento\Framework\Registry;
use Magento\Framework\Mail\Template\SenderResolverInterface;

/**
 * Class Message
 * @package Mageplaza\Smtp\Plugin
 */
class Message
{
    protected $registry;
    protected $__senderResolver;

    public function __construct(
        Registry $registry,
        SenderResolverInterface $senderResolver
    )
    {

        $this->registry = $registry;
        $this->_senderResolver = $senderResolver;
    }

    /**
     * @param \Magento\Framework\Mail\Template\TransportBuilderByStore $subject
     * @param $from
     * @param $store
     * @return array
     * @throws \Magento\Framework\Exception\MailException
     */
    public function beforeSetFromByStore(
        \Magento\Framework\Mail\Template\TransportBuilderByStore $subject,
        $from,
        $store
    )
    {
        $storeId = $this->registry->registry('mp_smtp_store_id');
        if (isset($storeId) == false) {
            $storeId = $store;
        }
        $email = $this->_senderResolver->resolve($from, $storeId);
        $this->registry->register("test", $email);

        return [$from, $store];
    }

}