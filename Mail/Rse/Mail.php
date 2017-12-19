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

namespace Mageplaza\Smtp\Mail\Rse;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
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
     * @var EncryptorInterface
     */
    protected $encryptor;

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
     * @var string message body email
     */
    protected $_message;

    /**
     * @var array option by storeid
     */
    protected $_smtpOptions;

    /**
     * Mail constructor.
     * @param null $options
     */
    public function __construct($options = null)
    {
        $this->smtpHelper = ObjectManager::getInstance()->get(Data::class);
        $this->encryptor  = ObjectManager::getInstance()->get(EncryptorInterface::class);

        parent::__construct($options);
    }

    /**
     * @param $storeId
     * @return mixed|null|\Zend_Mail_Transport_Abstract
     */
    public function getTransport($storeId)
    {
        if (!isset($this->_smtpOptions[$storeId])) {
            $configData                   = $this->smtpHelper->getConfig(Data::CONFIG_GROUP_SMTP, '', $storeId);
            $this->_smtpOptions[$storeId] = [
                'type'     => 'smtp',
                'host'     => isset($configData['host']) ? $configData['host'] : '',
                'ssl'      => isset($configData['protocol']) ? $configData['protocol'] : '',
                'port'     => isset($configData['port']) ? $configData['port'] : '',
                'auth'     => isset($configData['authentication']) ? $configData['authentication'] : '',
                'username' => isset($configData['username']) ? $configData['username'] : '',
                'password' => isset($configData['password']) ? $this->encryptor->decrypt($configData['password']) : '',
            ];
        }

        $this->_transport = null;
        $this->setOptions(['transport' => $this->_smtpOptions[$storeId]]);

        return $this->init();
    }

    /**
     * @param $message
     * @param $storeId
     * @return mixed
     */
    public function processMessage($message, $storeId)
    {
        if ($returnPath = $this->smtpHelper->getConfig(Data::CONFIG_GROUP_SMTP, 'return_path_email', $storeId)) {
            $message->setReturnPath($returnPath);
        }

        return $message;
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isModuleEnable($storeId)
    {
        if (!isset($this->_moduleEnable[$storeId])) {
            $this->_moduleEnable[$storeId] = $this->smtpHelper->isEnabled($storeId);
        }

        return $this->_moduleEnable[$storeId];
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isDeveloperMode($storeId)
    {
        if (!isset($this->_developerMode[$storeId])) {
            $this->_developerMode[$storeId] = $this->smtpHelper->getConfig(Data::DEVELOP_GROUP_SMTP, 'developer_mode', $storeId);
        }

        return $this->_developerMode[$storeId];
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isEnableEmailLog($storeId)
    {
        if (!isset($this->_emailLog[$storeId])) {
            $this->_emailLog[$storeId] = $this->smtpHelper->getConfig(Data::DEVELOP_GROUP_SMTP, 'log_email', $storeId);
        }

        return $this->_emailLog[$storeId];
    }
}
