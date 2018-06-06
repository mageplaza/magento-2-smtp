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
 * @package     Mageplaza_RewardPoints
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp;

use Magento\Backend\App\Action;
use Mageplaza\Smtp\Model\LogFactory;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Delete
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class Delete extends Action
{

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * Delete constructor.
     * @param Action\Context $context
     * @param LogFactory $logFactory
     */
    public function __construct(
        LogFactory $logFactory,
        Action\Context $context
    )
    {
        parent::__construct($context);

        $this->logFactory = $logFactory;
    }

    /**
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $logId = $this->getRequest()->getParam('id');
            $this->logFactory->create()->load($logId)->delete();
        } catch (\Exception $e) {
            $this->messageManager->addError(
                __('We can\'t process your request right now. %1', $e->getMessage())
            );
            $this->_redirect('*/smtp/log');
            return;
        }

        $this->messageManager->addSuccessMessage(
            __('A total of 1 record have been deleted.')
        );

        return $resultRedirect->setPath('*/smtp/log');
    }
}
