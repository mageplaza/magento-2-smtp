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
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;

/**
 * Class MassResend
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class MassResend extends Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $emailLog;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * MassResend constructor.
     *
     * @param Filter $filter
     * @param Action\Context $context
     * @param CollectionFactory $emailLog
     */
    public function __construct(
        Filter $filter,
        Action\Context $context,
        CollectionFactory $emailLog
    ) {
        $this->filter   = $filter;
        $this->emailLog = $emailLog;

        parent::__construct($context);
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->emailLog->create());
        $resend     = 0;

        /** @var \Mageplaza\Smtp\Model\Log $item */
        foreach ($collection->getItems() as $item) {
            if ($item->resendEmail()) {
                $resend++;
            } else {
                $this->messageManager->addErrorMessage(
                    __('We can\'t process your request for email log #%1', $item->getId())
                );
            }
        }

        if ($resend) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 record(s) have been sent.', $resend)
            );
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('adminhtml/smtp/log');
    }
}
