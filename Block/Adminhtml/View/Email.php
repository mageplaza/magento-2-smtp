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

namespace Mageplaza\Smtp\Block\Adminhtml\View;

/**
 * Class Email
 * @package Mageplaza\Smtp\Block\Adminhtml\View
 */
class Email extends \Magento\Backend\Block\Template
{
	/**
	 * @var string
	 */
	protected $_template = 'view/email.phtml';

	/**
	 * @var \Mageplaza\Smtp\Model\LogFactory
	 */
	private $logFactory;

	/**
	 * Constructor
	 *
	 * @param \Magento\Backend\Block\Template\Context $context
	 * @param \Mageplaza\Smtp\Model\LogFactory $logFactory
	 * @param array $data
	 */
	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		\Mageplaza\Smtp\Model\LogFactory $logFactory,
		array $data = []
	)
	{
		parent::__construct($context, $data);
		$this->logFactory = $logFactory;
	}

	/**
	 * Get email content
	 *
	 * @return string
	 */
	public function getContent()
	{
		$content = htmlspecialchars_decode($this->getLog()->getEmailContent());
		$content = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $content);

		return $content;
	}

	/**
	 * Load email log by id
	 *
	 * @return mixed
	 */
	public function getLog()
	{
		$logId = $this->getRequest()->getParam('id');
		$log   = $this->logFactory->create()->load($logId);
		if ($log) {
			return $log;
		}

		return false;
	}

	/**
	 * @return AbstractBlock
	 */
	protected function _prepareLayout()
	{
		if ($this->getToolbar()) {
			$this->getToolbar()->addChild(
				'back_button',
				'Magento\Backend\Block\Widget\Button',
				[
					'label'   => __('Back'),
					'onclick' => 'setLocation(\'' . $this->getUrl('*/*/log') . '\')',
					'class'   => 'back'
				]
			);
		}

		return parent::_prepareLayout();
	}
}
