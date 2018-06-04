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
 * @copyright   Copyright (c) 2018 Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp;

use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;
use Mageplaza\Smtp\Controller\Adminhtml\Resend\Email;
use Mageplaza\Smtp\Model\LogFactory;

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
     * @var Email
     */
    protected $resendEmail;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * MassResend constructor.
     * @param Action\Context $context
     * @param Filter $filter
     * @param CollectionFactory $emailLog
     * @param Email $resendEmail
     * @param LogFactory $logFactory
     */
    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $emailLog,
        Email $resendEmail,
        LogFactory $logFactory
    )
    {
        $this->filter = $filter;
        $this->emailLog = $emailLog;
        $this->resendEmail = $resendEmail;
        $this->logFactory = $logFactory;
        parent::__construct($context);
    }

    /**
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $collection = $this->filter->getCollection($this->emailLog->create());
            $logFactory = $this->logFactory->create();

            $resend = 0;
            foreach ($collection->getItems() as $item) {
                $data = $logFactory->load($item->getId())->getData();
                $data['email_content'] = htmlspecialchars_decode($data['email_content']);
                $this->resendEmail->resendEmail($data);

                $resend++;
            }
        } catch (\Exception $e) {
            $this->messageManager->addError(
                __('We can\'t process your request right now. '.$e->getMessage())
            );
            $this->_redirect('adminhtml/smtp/log');
            return;
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 record(s) have been sent.', $resend)
        );

        return $resultRedirect->setPath('adminhtml/smtp/log');
    }
}
