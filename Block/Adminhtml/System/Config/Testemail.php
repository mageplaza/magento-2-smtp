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

namespace Mageplaza\Smtp\Block\Adminhtml\System\Config;

/**
 * Class Testemail
 * @package Mageplaza\Smtp\Block\Adminhtml\System\Config
 */
class Testemail extends \Magento\Config\Block\System\Config\Form\Field
{
	/**
	 * @var string
	 */
	protected $_buttonLabel = '';

	/**
	 * Set template
	 *
	 * @return void
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('Mageplaza_Smtp::system/config/testemail.phtml');
	}

	/**
	 * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
	 * @return string
	 */
	protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
	{
		$originalData = $element->getOriginalData();
		$buttonLabel  = !empty($originalData['button_label']) ? $originalData['button_label'] : $this->_buttonLabel;
		$this->addData(
			[
				'button_label' => __($buttonLabel),
				'html_id'      => $element->getHtmlId(),
				'ajax_url'     => $this->_urlBuilder->getUrl('mageplaza_smtp/index/index'),
			]
		);

		return $this->_toHtml();
	}
}
