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
use Mageplaza\Smtp\Helper\AbandonedCart;

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
     * @var AbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * TestConnection constructor.
     * @param Context $context
     * @param AbandonedCart $helperAbandonedCart
     */
    public function __construct(
        Context $context,
        AbandonedCart $helperAbandonedCart
    ) {
        $this->helperAbandonedCart = $helperAbandonedCart;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {

            $result    = [
                'status'  => true,
                'content' => __('Email marketing connection is working properly.')
            ];
            $appID     = $this->getRequest()->getParam('appID');
            $secretKey = $this->getRequest()->getParam('secretKey');
            $this->helperAbandonedCart->testConnection($appID, $secretKey);

        } catch (Exception $e) {
            $result = [
                'status'  => false,
                'content' => __('Can\'t connect to the email marketing app. Please check the app id and secret key.')
            ];
        }

        return $this->getResponse()->representJson(AbandonedCart::jsonEncode($result));
    }
}
