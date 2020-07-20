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
use Magento\Email\Model\Template\SenderResolver;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\View\Result\Page;
use Mageplaza\Smtp\Helper\Data as SmtpData;
use Magento\Quote\Model\QuoteFactory;

/**
 * Class Preview
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\AbandonedCart
 */
class Preview extends Action
{
    /**
     * Template Factory
     *
     * @var FactoryInterface
     */
    protected $templateFactory;

    /**
     * @var SenderResolver
     */
    protected $senderResolver;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * Preview constructor.
     *
     * @param Context $context
     * @param FactoryInterface $templateFactory
     * @param SenderResolver $senderResolver
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(
        Context $context,
        FactoryInterface $templateFactory,
        SenderResolver $senderResolver,
        QuoteFactory $quoteFactory
    ) {
        $this->templateFactory = $templateFactory;
        $this->senderResolver  = $senderResolver;
        $this->quoteFactory    = $quoteFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|Page
     */
    public function execute()
    {
        $from         = $this->getRequest()->getParam('from');
        $templateId   = $this->getRequest()->getParam('template_id');
        $quoteId      = $this->getRequest()->getParam('quote_id');
        $customerName = $this->getRequest()->getParam('customer_name');
        $additionalMessage = $this->getRequest()->getParam('additional_message');

        $result = ['status' => false];

        try {
            $quote = $this->quoteFactory->create()->load($quoteId);
            if (!$quote->getId()) {
                throw NoSuchEntityException::singleField('quote_id', $quoteId);
            }

            $storeId  = $quote->getStoreId();
            $from     = $this->senderResolver->resolve($from, $storeId);
            $template = $this->templateFactory->get($templateId, null)
                ->setVars(
                    [
                        'quote_id'           => $quoteId,
                        'customer_name'      => ucfirst(trim($customerName)),
                        'additional_message' => trim(strip_tags($additionalMessage)),
                        'cart_recovery_link' => '#'
                    ]
                )
                ->setOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId]);
            $content  = $template->processTemplate();
            $subject  = html_entity_decode((string) $template->getSubject(), ENT_QUOTES);

            $result = [
                'status'  => true,
                'subject' => $subject,
                'content' => $content,
                'from'    => $from
            ];
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $this->getResponse()->representJson(SmtpData::jsonEncode($result));
    }
}
