<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) 2017 Mageplaza (https://www.mageplaza.com/)
 * @license     http://mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Index;

/**
 * Class Index
 * @package Mageplaza\Smtp\Controller\Adminhtml\Index
 */
class Index extends \Magento\Backend\App\Action
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
	private $logger;

	/**
	 * @var \Magento\Framework\Encryption\EncryptorInterface
	 */
	private $encryptor;

	/**
	 * @var \Mageplaza\Smtp\Helper\Data
	 */
	private $smtpDataHelper;

	/**
	 * Constructor
	 *
	 * @param \Magento\Backend\App\Action\Context $context
	 * @param \Magento\Framework\Json\Helper\Data $jsonHelper
	 */
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		\Psr\Log\LoggerInterface $logger,
		\Mageplaza\Smtp\Helper\Data $smtpDataHelper
	)
	{
		$this->resultPageFactory = $resultPageFactory;
		$this->jsonHelper        = $jsonHelper;
		$this->logger            = $logger;
		$this->encryptor         = $encryptor;
		$this->smtpDataHelper    = $smtpDataHelper;
		parent::__construct($context);
	}

	/**
	 * Execute view action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		$params           = $this->getRequest()->getParams();
		$result           = [];
		$result['status'] = false;
		if ($params && $params['email']) {
			$config = [];
			$host   = $params['host'];
			if ($params['protocol']) {
				$config['ssl'] = $params['protocol'];
			}
			if ($params['port']) {
				$config['port'] = $params['port'];
			}
			$config['auth']     = $params['authentication'];
			$config['username'] = $params['username'];
			if ($params['password'] == '******') {
				$config['password'] = $this->encryptor->decrypt($this->smtpDataHelper->getConfig('configuration_option', 'password'));
			} else {
				$config['password'] = $params['password'];
			}
			$transport = new \Zend_Mail_Transport_Smtp($host, $config);
			$mail      = new \Zend_Mail();
			if ($params['username']) {
				$mail->setFrom($config['username']);
			}
			$mail->addTo($params['email']);
			$mail->setSubject(__('TEST EMAIL from Custom SMTP'));
			$body = "
            Your store has been connected with a custom SMTP successfully. Now you can Save Config and use this connection. \n\n
            Sent via SMTP by https://www.mageplaza.com
            ";

			$mail->setBodyText($body);
			try {
				$mail->send($transport);
				$result['status']  = true;
				$result['content'] = __('Sent successfully! Please check your email box.');
			} catch (\Exception $e) {
				$result['content'] = $e->getMessage();
				$this->logger->critical($e);
			}
		} else {
			$result['content'] = __('Test Error');
		}

		return $this->jsonResponse($result);
	}

	/**
	 * Create json response
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function jsonResponse($response = '')
	{
		return $this->getResponse()->representJson(
			$this->jsonHelper->jsonEncode($response)
		);
	}
}
