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
     * Test constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param SmtpData $smtpDataHelper
     * @param Mail $mailResource
     * @param TransportBuilder $transportBuilder
     * @param SenderResolver $senderResolver
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        SmtpData $smtpDataHelper,
        Mail $mailResource,
        TransportBuilder $transportBuilder,
        SenderResolver $senderResolver
    ) {
        $this->logger            = $logger;
        $this->smtpDataHelper    = $smtpDataHelper;
        $this->mailResource      = $mailResource;
        $this->_transportBuilder = $transportBuilder;
        $this->senderResolver    = $senderResolver;

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
            $config = [
                'type'       => 'smtp',
                'host'       => $params['host'],
                'auth'       => $params['authentication'],
                'username'   => $params['username'],
                'ignore_log' => true,
                'force_sent' => true
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

            $this->mailResource->setSmtpOptions(Store::DEFAULT_STORE_ID, $config);

            $from = $this->senderResolver->resolve(
                isset($params['from']) ? $params['from'] : $config['username'],
                $this->smtpDataHelper->getScopeId()
            );

            $this->_transportBuilder
                ->setTemplateIdentifier('mpsmtp_test_email_template')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => Store::DEFAULT_STORE_ID])
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
