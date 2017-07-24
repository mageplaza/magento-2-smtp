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

namespace Mageplaza\Smtp\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Clear
 * @package Mageplaza\Smtp\Controller\Adminhtml\Index
 */
class Clear extends \Magento\Backend\App\Action
{
	/**
	 * Authorization level of a basic admin session
	 *
	 * @see _isAllowed()
	 */
	const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

	/**
	 * @var \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory
	 */
	private $collectionLog;

	/**
	 * Constructor
	 *
	 * @param \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory $collectionLog
	 * @param Context $context
	 */
	public function __construct(
		\Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory $collectionLog,
		Context $context
	)
	{
		$this->collectionLog = $collectionLog;
		parent::__construct($context);
	}

	/**
	 * Clear Emails Log
	 *
	 * @return \Magento\Backend\Model\View\Result\Redirect
	 */
	public function execute()
	{
		$resultRedirect = $this->resultRedirectFactory->create();
		$collection     = $this->collectionLog->create();
		try {
			$collection->clearLog();
			$this->messageManager->addSuccess(__('Success'));
		} catch (LocalizedException $e) {
			$this->messageManager->addError($e->getMessage());
		} catch (\Exception $e) {
			$this->messageManager->addException($e, __('Something went wrong.'));
		}

		return $resultRedirect->setPath('*/*/log');
	}
}
