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

namespace Mageplaza\Smtp\Mail\Template;

use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Mail\Rse\Mail;

/**
 * Class TransportBuilder
 * @package Mageplaza\Smtp\Mail\Template
 */
class TransportBuilder
{
    /**
     * @var Registry $registry
     */

    protected $registry;

    /**
     * @var Mail
     */
    protected $resourceMail;

    /**
     * @var SenderResolverInterface
     */
    protected $senderResolver;

    /**
     * TransportBuilder constructor.
     *
     * @param Registry $registry
     * @param Mail $resourceMail
     * @param SenderResolverInterface $SenderResolver
     */
    public function __construct(
        Registry $registry,
        Mail $resourceMail,
        SenderResolverInterface $SenderResolver
    ) {
        $this->registry       = $registry;
        $this->resourceMail   = $resourceMail;
        $this->senderResolver = $SenderResolver;
    }

    /**
     * @param \Magento\Framework\Mail\Template\TransportBuilder $subject
     * @param $templateOptions
     *
     * @return array
     */
    public function beforeSetTemplateOptions(
        \Magento\Framework\Mail\Template\TransportBuilder $subject,
        $templateOptions
    ) {
        $this->registry->unregister('mp_smtp_store_id');
        if (array_key_exists('store', $templateOptions)) {
            $this->registry->register('mp_smtp_store_id', $templateOptions['store']);
        }

        return [$templateOptions];
    }

    /**
     * @param \Magento\Framework\Mail\Template\TransportBuilder $subject
     * @param $from
     *
     * @return array
     * @throws MailException
     */
    public function beforeSetFrom(\Magento\Framework\Mail\Template\TransportBuilder $subject, $from)
    {
        $result = $from;
        if (is_string($from)) {
            $result = $this->senderResolver->resolve($from);
        }
        if (is_array($from)) {
            $result = $from;
        }
        $this->resourceMail->setFromByStore($result['email'], $result['name']);

        return [$from];
    }
}
