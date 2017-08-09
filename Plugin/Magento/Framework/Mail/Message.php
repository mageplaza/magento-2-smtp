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
	 * Constructor
	 *
	 * @param \Magento\Framework\Registry $registry
	 */
	public function __construct(
		\Magento\Framework\Registry $registry
	)
	{
		$this->registry = $registry;
	}

	/**
	 * Register $this
	 *
	 * @return $this
	 */
	public function afterSetBody(\Magento\Framework\Mail\Message $subject, $result)
	{
		$this->registry->unregister('mageplaza_smtp_message');
		$this->registry->register('mageplaza_smtp_message', $subject);

		return $result;
	}
}
