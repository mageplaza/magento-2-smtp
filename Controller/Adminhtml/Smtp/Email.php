<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Model\LogFactory;

/**
 * Class Email
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class Email extends Action
{
    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * Email constructor.
     *
     * @param Context $context
     * @param LogFactory $logFactory
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        LogFactory $logFactory,
        StateInterface $inlineTranslation,
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager
    ) {
        $this->logFactory        = $logFactory;
        $this->scopeConfig       = $scopeConfig;
        $this->storeManager      = $storeManager;
        $this->_transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $logId = $this->getRequest()->getParam('id');
        if (!$logId) {
            return $this->_redirect('*/*/log');
        }

        $email = $this->logFactory->create()->load($logId);
        if ($email->resendEmail()) {
            $this->messageManager->addSuccessMessage(__('Email re-sent successfully!'));
        } else {
            $this->messageManager->addErrorMessage(__('We can\'t process your request right now.'));
        }
        $this->_redirect('*/smtp/log');
    }
}
