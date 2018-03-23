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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Smtp\Helper\Data as SmtpData;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Index
 * @package Mageplaza\Smtp\Controller\Adminhtml\Index
 */
class Index extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     * Sender resolver
     *
     * @var \Magento\Framework\Mail\Template\SenderResolverInterface
     */
    protected $_senderResolver;

    /**
     * @var \Mageplaza\Smtp\Helper\Data
     */
    protected $smtpDataHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Index constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Mail\Template\SenderResolverInterface $senderResolver
     * @param \Mageplaza\Smtp\Helper\Data $smtpDataHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $jsonHelper,
        EncryptorInterface $encryptor,
        LoggerInterface $logger,
        SenderResolverInterface $senderResolver,
        SmtpData $smtpDataHelper,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->_senderResolver = $senderResolver;
        $this->smtpDataHelper = $smtpDataHelper;
        $this->scopeConfig = $scopeConfig;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Zend_Mail_Exception
     */
    public function execute()
    {
        $result = ['status' => false];

        $params = $this->getRequest()->getParams();
        if ($params && $params['to']) {
            $config = [];
            $host = $params['host'];
            if ($params['protocol']) {
                $config['ssl'] = $params['protocol'];
            }
            if ($params['port']) {
                $config['port'] = $params['port'];
            }
            $config['auth'] = $params['authentication'];
            $config['username'] = $params['username'];
            if ($params['password'] == '******') {
                $config['password'] = $this->encryptor->decrypt($this->getPasswordFromConfig());
            } else {
                $config['password'] = $params['password'];
            }

            $transport = new \Zend_Mail_Transport_Smtp($host, $config);
            $mail = new \Zend_Mail();

            if ($params['from']) {
                $result = $this->_senderResolver->resolve($params['from']);
                $mail->setFrom($result['email'], $result['name']);
            } else {
                $mail->setFrom($config['username']);
            }

            if ($params['returnpath']) {
                $mail->setReturnPath($params['returnpath']);
            }

            $mail->addTo($params['to']);
            $mail->setSubject(__('TEST EMAIL from Custom SMTP'));
            $mail->setBodyText("Your store has been connected with a custom SMTP successfully. Now you can Save Config and use this connection. \n\n
            Sent via SMTP by https://www.mageplaza.com");

            try {
                $mail->send($transport);
                $result['status'] = true;
                $result['content'] = __('Sent successfully! Please check your email box.');
            } catch (\Exception $e) {
                $result['content'] = $e->getMessage();
                $this->logger->critical($e);
            }
        } else {
            $result['content'] = __('Test Error');
        }

        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($result)
        );
    }

    private function getPasswordFromConfig()
    {
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeCode = null;

        $website = $this->getRequest()->getParam('website');
        if ($website)
        {
            $scope = ScopeInterface::SCOPE_WEBSITE;
            $scopeCode = $website;
        }

        $store = $this->getRequest()->getParam('store');
        if ($store)
        {
            $scope = ScopeInterface::SCOPE_STORE;
            $scopeCode = $store;
        }

        $path = sprintf('%s/configuration_option/password', \Mageplaza\Smtp\Helper\Data::CONFIG_MODULE_PATH);
        return $this->scopeConfig->getValue($path, $scope, $scopeCode);
    }
}
