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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\QuoteRepository;

/**
 * Class View
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp\AbandonedCart
 */
class View extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var Random
     */
    protected $random;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Registry $registry
     * @param QuoteRepository $quoteRepository
     * @param Random $random
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $registry,
        QuoteRepository $quoteRepository,
        Random $random
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->registry          = $registry;
        $this->quoteRepository   = $quoteRepository;
        $this->random            = $random;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $id         = $this->getRequest()->getParam('id', 0);
        $quote      = $this->quoteRepository->get($id);
        $isActive   = (bool) $quote->getIsActive();
        if (!$isActive) {
            return $this->_redirect('adminhtml/smtp/abandonedcart');
        }

        if (!$quote->getData('mp_smtp_ace_token')) {
            $quote->setData('mp_smtp_ace_token', $this->random->getUniqueHash())->save();
        }

        if ($quote->getIsActive()) {
            $this->messageManager->addNoticeMessage(__('Cart recovery email is not sent to the customer yet.'));
        }

        $params                    = $this->getRequest()->getParams();
        $params['quote_is_active'] = $quote->getIsActive();
        $this->getRequest()->setParams($params);

        /** @var Page $resultPage */
        $resultPage->getConfig()->getTitle()->prepend(__('Abandoned Cart #%1', $quote->getId()));
        $this->registry->register('quote', $quote);

        return $resultPage;
    }
}
