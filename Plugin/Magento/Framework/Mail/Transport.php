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

namespace Mageplaza\Smtp\Plugin\Magento\Framework\Mail;

/**
 * Class Transport
 * @package Mageplaza\Smtp\Plugin\Magento\Framework\Mail
 */
class Transport extends \Zend_Mail_Transport_Smtp
{
	const CONFIGURATION_GROUP_SMTP = 'configuration_option';
	const DEVELOPER_GROUP_SMTP = 'developer';
	const GENERAL_GROUP_SMTP = 'general';

	/**
	 * @var \Mageplaza\Smtp\Helper\Data
	 */
	private $smtpDataHelper;

	/**
	 * @var \Mageplaza\Smtp\Model\LogFactory
	 */
	private $logFactory;

	/**
	 * @var \Magento\Framework\Registry
	 */
	private $registry;

	/**
	 * @var \Magento\Framework\Encryption\EncryptorInterface
	 */
	private $encryptor;

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
	 * @param \Mageplaza\Smtp\Model\LogFactory $logFactory
	 * @param \Mageplaza\Smtp\Helper\Data $smtpDataHelper
	 */
	public function __construct(
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		\Mageplaza\Smtp\Model\LogFactory $logFactory,
		\Mageplaza\Smtp\Helper\Data $smtpDataHelper
	)
	{
		$this->registry       = $registry;
		$this->smtpDataHelper = $smtpDataHelper;
		$this->logFactory     = $logFactory;
		$this->encryptor      = $encryptor;
	}

	/**
	 * @param \Magento\Framework\Mail\TransportInterface $subject
	 * @param \Closure $proceed
	 * @throws \Magento\Framework\Exception\MailException
	 */
	public function aroundSendMessage(\Magento\Framework\Mail\TransportInterface $subject, \Closure $proceed)
	{
		$config = [];
		if ($this->smtpDataHelper->getConfig(self::GENERAL_GROUP_SMTP, 'enabled')) {
			$message = $this->registry->registry('mageplaza_smtp_message');
			if ($host = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'host')) {
				$this->_host = $host;
			}
			if ($returnPath = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'return_path_email')) {
				$message->setReturnPath($returnPath);
			}
			if ($protocol = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'protocol')) {
				$config['ssl'] = $protocol;
			}

			$port = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'port');
			if ($port) {
				$config['port'] = $port;
				$this->_port    = $port;
			}

			$auth        = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'authentication');
			$this->_auth = $auth;

			$config['auth']     = $auth;
			$config['username'] = $this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'username');
			$config['password'] = $this->encryptor->decrypt($this->smtpDataHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'password'));

			$headers    = $message->getHeaders();
			$senderName = strip_tags($headers['From'][0], $message->getFrom());
			if ($config['username'] && $senderName) {
				$message->clearFrom();
				$message->setFrom($config['username'], $senderName);
			}
			if (!empty($config)) {
				$this->_config = $config;
			}
			try {
				if (!$this->smtpDataHelper->getConfig(self::DEVELOPER_GROUP_SMTP, 'developer_mode')) {
					parent::send($message);
				}
				$this->emailLog($message);
			} catch (\Exception $e) {
				$this->emailLog($message, false);
				throw new \Magento\Framework\Exception\MailException(new \Magento\Framework\Phrase($e->getMessage()), $e);
			}
		} else {
			$proceed();
		}
	}

	/**
	 * Save Email Sent
	 *
	 * @param $message
	 * @param bool $status
	 * @throws \Magento\Framework\Exception\MailException
	 */
	private function emailLog($message, $status = true)
	{
		if ($this->smtpDataHelper->getConfig(self::DEVELOPER_GROUP_SMTP, 'log_email')) {
			$log = $this->logFactory->create();
			try {
				$log->saveLog($message, $status);
			} catch (\Exception $e) {
				throw new \Magento\Framework\Exception\MailException(new \Magento\Framework\Phrase($e->getMessage()), $e);
			}
		}
	}
}
