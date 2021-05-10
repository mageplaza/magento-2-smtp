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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Config\Model\ResourceModel\Config as ModelConfig;
use Mageplaza\Smtp\Helper\Data;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class TestConnection
 * @package Mageplaza\Smtp\Controller\Adminhtml\Smtp
 */
class TestConnection extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Smtp::smtp';

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var ModelConfig
     */
    protected $modelConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * TestConnection constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param ModelConfig $modelConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        ModelConfig $modelConfig,
        EncryptorInterface $encryptor
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->modelConfig          = $modelConfig;
        $this->encryptor            = $encryptor;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $result    = [
                'status'  => true,
                'content' => __('Email marketing connection is working properly.')
            ];
            $appID     = trim($this->getRequest()->getParam('appID'));
            $secretKey = $this->getRequest()->getParam('secretKey');
            $request   = $this->helperEmailMarketing->testConnection($appID, $secretKey);
            $this->modelConfig->saveConfig(
                Data::EMAIL_MARKETING . '/general/connectToken',
                $this->encryptor->encrypt($request['data']['connectToken'])
            );

        } catch (Exception $e) {
            $result = [
                'status'  => false,
                'content' => __('Can\'t connect to the email marketing app. %1', $e->getMessage())
            ];
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }
}
