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
use Magento\Email\Model\Template\SenderResolver;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;
use Mageplaza\Smtp\Helper\Data as SmtpData;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Psr\Log\LoggerInterface;

/**
 * Class Test
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class Test extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SmtpData
     */
    protected $smtpDataHelper;

    /**
     * @var Mail
     */
    protected $mailResource;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var SenderResolver
     */
    protected $senderResolver;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * Test constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param SmtpData $smtpDataHelper
     * @param Mail $mailResource
     * @param TransportBuilder $transportBuilder
     * @param SenderResolver $senderResolver
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        SmtpData $smtpDataHelper,
        Mail $mailResource,
        TransportBuilder $transportBuilder,
        SenderResolver $senderResolver,
        EncryptorInterface $encryptor
    ) {
        $this->logger            = $logger;
        $this->smtpDataHelper    = $smtpDataHelper;
        $this->mailResource      = $mailResource;
        $this->_transportBuilder = $transportBuilder;
        $this->senderResolver    = $senderResolver;
        $this->encryptor         = $encryptor;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $result = ['status' => false];

        $params = $this->getRequest()->getParams();
        if ($params && $params['to']) {
            $storeId = $params['store'] ?? Store::DEFAULT_STORE_ID;
            $config  = [
                'type'           => 'smtp',
                'host'           => $params['host'],
                'auth'           => $params['authentication'],
                'authentication' => $params['authentication'],
                'username'       => $params['username'],
                'ignore_log'     => true,
                'force_sent'     => true
            ];

            if ($params['protocol']) {
                $config['ssl'] = $params['protocol'];
            }
            if ($params['port']) {
                $config['port'] = $params['port'];
            }
            if ($params['password'] === '******') {
                $config['password'] = $this->smtpDataHelper->getPassword();
            } else {
                $config['password'] = $params['password'];
            }
            if ($params['returnpath']) {
                $config['return_path'] = $params['returnpath'];
            }

            // Add OAuth2 parameters for Microsoft Graph API authentication
            if (isset($params['authentication']) && $params['authentication'] === 'oauth2') {
                if (!empty($params['oauth_tenant_id'])) {
                    $config['oauth_tenant_id'] = $params['oauth_tenant_id'];
                } else {
                    $config['oauth_tenant_id'] = $this->smtpDataHelper->getSmtpConfig('oauth_tenant_id', $storeId);
                }

                if (!empty($params['oauth_client_id'])) {
                    $config['oauth_client_id'] = $params['oauth_client_id'];
                } else {
                    $config['oauth_client_id'] = $this->smtpDataHelper->getSmtpConfig('oauth_client_id', $storeId);
                }

                if (!empty($params['oauth_client_secret']) && $params['oauth_client_secret'] !== '******') {
                    $config['oauth_client_secret'] = trim($params['oauth_client_secret']);
                } else {
                    $encryptedSecret = $this->smtpDataHelper->getSmtpConfig('oauth_client_secret', $storeId);
                    if ($encryptedSecret) {
                        $config['oauth_client_secret'] = trim($this->encryptor->decrypt($encryptedSecret));
                    }
                }

                if (!empty($params['oauth_scope'])) {
                    $config['oauth_scope'] = $params['oauth_scope'];
                } else {
                    $config['oauth_scope'] = $this->smtpDataHelper->getSmtpConfig(
                        'oauth_scope',
                        $storeId
                    ) ?: 'https://graph.microsoft.com/.default';
                }
            }

            unset($config['authentication']);
            $this->mailResource->setSmtpOptions($storeId, $config);

            $from = $this->senderResolver->resolve(
                isset($params['from']) ? $params['from'] : $config['username'],
                $this->smtpDataHelper->getScopeId()
            );

            $this->_transportBuilder
                ->setTemplateIdentifier('mpsmtp_test_email_template')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([])
                ->setFrom($from)
                ->addTo($params['to']);

            try {
                $this->_transportBuilder->getTransport()->sendMessage();

                $result = [
                    'status'  => true,
                    'content' => __('Sent successfully! Please check your email box.')
                ];
            } catch (Exception $e) {
                $result['content'] = $e->getMessage();
                $this->logger->critical($e);
            }
        } else {
            $result['content'] = __('Test Error');
        }

        return $this->getResponse()->representJson(SmtpData::jsonEncode($result));
    }
}
