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

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;

/**
 * Class Clear
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class Clear extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var CollectionFactory
     */
    protected $collectionLog;

    /**
     * Constructor
     *
     * @param CollectionFactory $collectionLog
     * @param Context $context
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionLog
    ) {
        $this->collectionLog = $collectionLog;

        parent::__construct($context);
    }

    /**
     * Clear Emails Log
     *
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        /** @var Collection $collection */
        $collection = $this->collectionLog->create();
        try {
            $collection->clearLog();
            $this->messageManager->addSuccess(__('Success'));
        } catch (LocalizedException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong.'));
        }

        return $resultRedirect->setPath('*/*/log');
    }
}
