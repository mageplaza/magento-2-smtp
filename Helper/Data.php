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

namespace Mageplaza\Smtp\Helper;

/**
 * Class Data
 * @package Mageplaza\Smtp\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	/**
	 * @var \Magento\Framework\App\Config\ScopeConfigInterface
	 */
	protected $scopeConfig;

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Helper\Context $context
	 */
	public function __construct(
		\Magento\Framework\App\Helper\Context $context
	)
	{
		parent::__construct($context);
		$this->scopeConfig = $context->getScopeConfig();
	}

	/**
	 * Get Config
	 *
	 * @param null $storeId
	 * @return mixed
	 */
	public function getConfig($group, $field, $storeId = null)
	{
		return $this->scopeConfig->getValue('smtp/' . $group . '/' . $field, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
	}
}
