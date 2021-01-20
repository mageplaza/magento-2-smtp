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

namespace Mageplaza\Smtp\Controller\Adminhtml\Smtp\AbandonedCart;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Email\Model\Template;
use Magento\Email\Model\Template\SenderResolver;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Quote\Model\QuoteRepository;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Psr\Log\LoggerInterface;

/**
 * Class Send
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\AbandonedCart
 */
class Send extends Action
{
    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var AreaList
     */
    protected $areaList;

    /**
     * @var Template
     */
    protected $emailTemplate;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SenderResolver
     */
    protected $senderResolver;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * Send constructor.
     *
     * @param Context $context
     * @param QuoteRepository $quoteRepository
     * @param AreaList $areaList
     * @param Template $emailTemplate
     * @param LoggerInterface $logger
     * @param SenderResolver $senderResolver
     * @param TransportBuilder $transportBuilder
     * @param Registry $registry
     * @param EmailMarketing $helperEmailMarketing
     */
    public function __construct(
        Context $context,
        QuoteRepository $quoteRepository,
        AreaList $areaList,
        Template $emailTemplate,
        LoggerInterface $logger,
        SenderResolver $senderResolver,
        TransportBuilder $transportBuilder,
        Registry $registry,
        EmailMarketing $helperEmailMarketing
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->emailTemplate = $emailTemplate;
        $this->areaList = $areaList;
        $this->senderResolver = $senderResolver;
        $this->transportBuilder = $transportBuilder;
        $this->registry = $registry;
        $this->helperEmailMarketing = $helperEmailMarketing;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id', 0);

        try {
            $quote = $this->quoteRepository->get($id);
            $customerEmail = $quote->getCustomerEmail();
            $customerName = $this->helperEmailMarketing->getCustomerName($quote);

            $from = $this->getRequest()->getParam('sender');
            $templateId = $this->getRequest()->getParam('email_template');
            $additionalMessage = $this->getRequest()->getParam('additional_message');
            $from = $this->senderResolver->resolve($from, $quote->getStoreId());
            $recoveryUrl = $this->helperEmailMarketing->getRecoveryUrl($quote);

            $vars = [
                'quote_id' => $quote->getId(),
                'customer_name' => ucfirst($customerName),
                'additional_message' => trim(strip_tags($additionalMessage)),
                'cart_recovery_link' => $recoveryUrl
            ];

            $areaObject = $this->areaList->getArea($this->emailTemplate->getDesignConfig()->getArea());
            $areaObject->load(Area::PART_TRANSLATE);

            $transport = $this->transportBuilder->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $quote->getStoreId()])
                ->setFrom($from)
                ->addTo($customerEmail, $customerName)
                ->setTemplateVars($vars)
                ->getTransport();

            $this->registry->register('smtp_abandoned_cart', $quote);
            $transport->sendMessage();
            $this->messageManager->addSuccessMessage(__('Cart recovery email was sent to the customer successfully!'));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Cart recovery email cannot sent to the customer.'));
            $this->logger->error($e->getMessage());
        }

        return $this->_redirect('adminhtml/smtp_abandonedcart/view', ['id' => $id]);
    }
}
