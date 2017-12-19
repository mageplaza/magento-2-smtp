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
    /**
     * @var \Mageplaza\Smtp\Helper\Data
     */
    protected $smtpHelper;

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
        $this->smtpHelper = ObjectManager::getInstance()->get(Data::class);
        $encryptor        = ObjectManager::getInstance()->get(EncryptorInterface::class);

        $configData = $this->smtpHelper->getConfig(Data::CONFIG_GROUP_SMTP);
        $options    = [
            'type'     => 'smtp',
            'host'     => isset($configData['host']) ? $configData['host'] : '',
            'ssl'      => isset($configData['protocol']) ? $configData['protocol'] : '',
            'port'     => isset($configData['port']) ? $configData['port'] : '',
            'auth'     => isset($configData['authentication']) ? $configData['authentication'] : '',
            'username' => isset($configData['username']) ? $configData['username'] : '',
            'password' => isset($configData['password']) ? $encryptor->decrypt($configData['password']) : '',
        ];

        parent::__construct(['transport' => $options]);
    }

    /**
     * @return \Magento\Framework\Mail\Message
     */
    public function getMessage()
    {
        $registry = ObjectManager::getInstance()->get(Registry::class);
        $message  = $registry->registry('mageplaza_smtp_message');
        if ($returnPath = $this->smtpHelper->getConfig(Data::CONFIG_GROUP_SMTP, 'return_path_email')) {
            $message->setReturnPath($returnPath);
        }

        return $message;
    }

    /**
     * @return bool|mixed
     */
    public function isModuleEnable()
    {
        if (is_null($this->_moduleEnable)) {
            $this->_moduleEnable = $this->smtpHelper->isEnabled();
        }

        return $this->_moduleEnable;
    }

    /**
     * @return bool|mixed
     */
    public function isDeveloperMode()
    {
        if (is_null($this->_developerMode)) {
            $this->_developerMode = $this->smtpHelper->getConfig(Data::DEVELOP_GROUP_SMTP, 'developer_mode');
        }

        return $this->_developerMode;
    }

    /**
     * @return bool|mixed
     */
    public function isEnableEmailLog()
    {
        if (is_null($this->_emailLog)) {
            $this->_emailLog = $this->smtpHelper->getConfig(Data::DEVELOP_GROUP_SMTP, 'log_email');
        }

        return $this->_emailLog;
    }
}
