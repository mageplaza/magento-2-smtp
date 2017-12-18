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

namespace Mageplaza\Smtp\Application\Rse;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;

/**
 * Class Mail
 * @package Mageplaza\Smtp\Application\Rse
 */
class Mail extends \Zend_Application_Resource_Mail
{
	const CONFIGURATION_GROUP_SMTP = 'configuration_option';
	const DEVELOPER_GROUP_SMTP = 'developer';
	const GENERAL_GROUP_SMTP = 'general';

	/**
	 * @var \Mageplaza\Smtp\Helper\Data
	 */
	protected $smtpHelper;

	/**
	 * @var \Magento\Framework\Registry
	 */
	protected $registry;

	/**
	 * @var boolean is module enable
	 */
	protected $_moduleEnable;

	/**
	 * @var boolean is developer mode
	 */
	protected $_developerMode;

	/**
	 * @var boolean is enable email log
	 */
	protected $_emailLog;

	/**
	 * Mail constructor.
	 * @param null $options
	 */
	public function __construct($options = null)
	{
		$options = ['type' => 'smtp'];

		$smtpHelper = ObjectManager::getInstance()->get(Data::class);
		if ($host = $smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'host')) {
			$options['host'] = $host;
		}

		if ($protocol = $smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'protocol')) {
			$options['ssl'] = $protocol;
		}

		if ($port = $smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'port')) {
			$options['port'] = $port;
		}

		$options['auth']     = $smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'authentication');
		$options['username'] = $smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'username');

		$encryptor           = ObjectManager::getInstance()->get(EncryptorInterface::class);
		$options['password'] = $encryptor->decrypt($smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'password'));

		$this->smtpHelper = $smtpHelper;
		$this->registry   = ObjectManager::getInstance()->get(Registry::class);

		parent::__construct(['transport' => $options]);
	}

	/**
	 * @return \Magento\Framework\Mail\Message
	 */
	public function getMessage()
	{
		//set return-path
		$message = $this->registry->registry('mageplaza_smtp_message');
		if ($returnPath = $this->smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'return_path_email')) {
            $message->setReturnPath($returnPath);
        }

        //set email from
		$headers    = $message->getHeaders();
		$senderName = strip_tags($headers['From'][0], $message->getFrom());
		$fromPath   = $this->smtpHelper->getConfig(self::CONFIGURATION_GROUP_SMTP, 'email_from');
		if ($fromPath && $senderName) {
			$message->clearFrom();
			$message->setFrom($fromPath, $senderName);
		}

		return $message;
	}

	/**
	 * @return bool|mixed
	 */
	public function isModuleEnable()
	{
		if (is_null($this->_moduleEnable)) {
			$this->_moduleEnable = $this->smtpHelper->getConfig(self::GENERAL_GROUP_SMTP, 'enabled');
		}

		return $this->_moduleEnable;
	}

	/**
	 * @return bool|mixed
	 */
	public function isDeveloperMode()
	{
		if (is_null($this->_developerMode)) {
			$this->_developerMode = $this->smtpHelper->getConfig(self::DEVELOPER_GROUP_SMTP, 'developer_mode');
		}

		return $this->_developerMode;
	}

	/**
	 * @return bool|mixed
	 */
	public function isEnableEmailLog()
	{
		if (is_null($this->_emailLog)) {
			$this->_emailLog = $this->smtpHelper->getConfig(self::DEVELOPER_GROUP_SMTP, 'log_email');
		}

		return $this->_emailLog;
	}
}
