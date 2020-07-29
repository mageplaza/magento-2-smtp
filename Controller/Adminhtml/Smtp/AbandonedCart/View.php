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
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Smtp\Model\AbandonedCartFactory;
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
     * @var AbandonedCartFactory
     */
    protected $abandonedCartFactory;

    /**
     * @var Registry
     */
    protected $registry;

    protected $quoteRepository;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Registry $registry
     * @param AbandonedCartFactory $abandonedCartFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $registry,
        AbandonedCartFactory $abandonedCartFactory,
        QuoteRepository $quoteRepository
    ) {
        $this->resultPageFactory    = $resultPageFactory;
        $this->abandonedCartFactory = $abandonedCartFactory;
        $this->registry             = $registry;
        $this->quoteRepository = $quoteRepository;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|Page
     */
    public function execute()
    {
        $resultPage    = $this->resultPageFactory->create();
        $id            = $this->getRequest()->getParam('id', 0);
        $abandonedCart = $this->abandonedCartFactory->create();
        $abandonedCart->load($id);

        if ($abandonedCart->getId()) {
            $quote                     = $this->quoteRepository->get($abandonedCart->getQuoteId());
            $params                    = $this->getRequest()->getParams();
            $params['quote_is_active'] = $quote->getIsActive();
            $this->getRequest()->setParams($params);
            if (!$abandonedCart->getStatus() && $quote->getIsActive()) {
                $this->messageManager->addNoticeMessage(__('Cart recovery email is not sent to the customer yet.'));
            }

            /** @var Page $resultPage */
            $resultPage->getConfig()->getTitle()->prepend(__('Abandoned Cart #%1', $abandonedCart->getId()));
            $this->registry->register('abandonedCart', $abandonedCart);

            return $resultPage;
        }

        return $this->_redirect('adminhtml/smtp/abandonedcart');
    }
}
