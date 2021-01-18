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
     * TestConnection constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {

            $result = [
                'status' => true,
                'content' => __('Email marketing connection is working properly.')
            ];
            $appID = trim($this->getRequest()->getParam('appID'));
            $secretKey = $this->getRequest()->getParam('secretKey');
            $this->helperEmailMarketing->testConnection($appID, $secretKey);

        } catch (Exception $e) {
            $result = [
                'status' => false,
                'content' => __('Can\'t connect to the email marketing app. Please check the app id and secret key.')
            ];
        }

        return $this->getResponse()->representJson(EmailMarketing::jsonEncode($result));
    }
}
