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

namespace Mageplaza\Smtp\Plugin\Magento\Framework\Mail;

use Magento\Framework\Mail\Message as MailMessage;
use Magento\Framework\Registry;

/**
 * Class Message
 * @package Mageplaza\Smtp\Plugin\Magento\Framework\Mail
 */
class Message
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Message constructor.
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Framework\Mail\Message $subject
     * @param $result
     * @return mixed
     */
    public function afterSetBody(MailMessage $subject, $result)
    {
        $this->registry->unregister('mageplaza_smtp_message');
        $this->registry->register('mageplaza_smtp_message', $subject);

        return $result;
    }
}
