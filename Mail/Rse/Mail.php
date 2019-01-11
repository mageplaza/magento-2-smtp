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

namespace Mageplaza\Smtp\Mail\Rse;

use Magento\Framework\App\ObjectManager;
use Mageplaza\Smtp\Helper\Data;
use Zend\Mail\Transport\Smtp;
use Zend\Mail\Transport\SmtpOptions as SmtpOptions;

/**
 * Class Mail
 * @package Mageplaza\Smtp\Application\Rse
 */
class Mail extends \Zend_Mail
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
    protected $_emailLog = [];

    /**
     * @var string message body email
     */
    protected $_message;

    /**
     * @var array option by storeid
     */
    protected $_smtpOptions = [];

    /**
     * @var array
     */
    protected $_returnPath = [];

    /**
     * @var option
     */
    protected $_option = array();

    /**
     * @var transport
     */
    protected $_transport;

    /**
     * Mail constructor.
     * @param null $options
     */
    public function __construct($options = null)
    {
        $this->smtpHelper = ObjectManager::getInstance()->get(Data::class);

        parent::__construct($options);
    }

    /**
     * @param $storeId
     * @param array $options
     * @return $this
     */
    public function setSmtpOptions($storeId, $options = [])
    {
        if (isset($options['return_path'])) {
            $this->_returnPath[$storeId] = $options['return_path'];
            unset($options['return_path']);
        }

        if (isset($options['ignore_log']) && $options['ignore_log']) {
            $this->_emailLog[$storeId] = false;
            unset($options['ignore_log']);
        }

        if (isset($options['force_sent']) && $options['force_sent']) {
            $this->_moduleEnable[$storeId] = true;
            unset($options['force_sent']);
        }

        if (sizeof($options)) {
            $this->_smtpOptions[$storeId] = $options;
        }
        return $this;
    }

    /**
     * @param $storeId
     * @return transport|\Zend_Mail_Transport_Smtp
     * @throws \Zend_Exception
     */
    public function getTransportZend($storeId)
    {
        $configData = $this->smtpHelper->getSmtpConfig('', $storeId);
        $host = isset($configData['host']) ? $configData['host'] : '';
        $authentication = isset($configData['authentication']) ? $configData['authentication'] : '';
        $protocol = isset($configData['protocol']) ? $configData['protocol'] : '';
        if ($host == "") {
            throw new \Zend_Exception('A host is necessary for smtp transport,' . ' but none was given');
        }

        if (!isset($this->_smtpOptions[$storeId])) {
            if ($authentication !== "") {
                $this->_smtpOptions[$storeId] = [
                    'type' => 'smtp',
                    'port' => isset($configData['port']) ? $configData['port'] : '',
                    'auth' => $authentication,
                    'username' => isset($configData['username']) ? $configData['username'] : '',
                    'password' => $this->smtpHelper->getPassword($storeId)
                ];
            } else {
                $this->_smtpOptions[$storeId] = [
                    'type' => 'smtp',
                    'port' => isset($configData['port']) ? $configData['port'] : ''
                ];
            }
            if ($protocol !== "") {
                $this->_smtpOptions[$storeId]['ssl'] = $configData['protocol'];
            }
        }

        $this->_transport = null;
        $this->_option = $this->_smtpOptions[$storeId];
        $this->_transport = new \Zend_Mail_Transport_Smtp($host, $this->_option);

        return $this->_transport;
    }

    /**
     * @param $storeId
     * @return transport|Smtp
     * @throws \Zend_Exception
     */
    public function getTransportZendNewVersion($storeId)
    {
        $configData = $this->smtpHelper->getSmtpConfig('', $storeId);
        $host = isset($configData['host']) ? $configData['host'] : '';
        $authentication = isset($configData['authentication']) ? $configData['authentication'] : '';
        if ($host == "") {
            throw new \Zend_Exception('A host is necessary for smtp transport,' . ' but none was given');
        }

        $this->_transport = null;
        if ($configData['authentication'] !== "") {
            if ($configData['protocol'] !== "") {
                $this->_option = new SmtpOptions([
                    'host' => $host,
                    'port' => isset($configData['port']) ? $configData['port'] : '',
                    'connection_class' => $authentication,
                    'connection_config' =>
                        [
                            'username' => isset($configData['username']) ? $configData['username'] : '',
                            'password' => $this->smtpHelper->getPassword($storeId),
                            'ssl' => $configData['protocol']
                        ]
                ]);
            } else {
                $this->_option = new SmtpOptions([
                    'host' => $host,
                    'port' => isset($configData['port']) ? $configData['port'] : '',
                    'connection_class' => $configData['authentication'],
                    'connection_config' =>
                        [
                            'username' => isset($configData['username']) ? $configData['username'] : '',
                            'password' => $this->smtpHelper->getPassword($storeId)
                        ]
                ]);
            }
        } else {
            $this->_option = new SmtpOptions([
                'host' => $host,
                'port' => isset($configData['port']) ? $configData['port'] : ''
            ]);
        }
        $this->_transport = new Smtp($this->_option);

        return $this->_transport;
    }

    /**
     * @param $message
     * @param $storeId
     * @return mixed
     */
    public function processMessage($message, $storeId)
    {
        if (!isset($this->_returnPath[$storeId])) {
            $this->_returnPath[$storeId] = $this->smtpHelper->getSmtpConfig('return_path_email', $storeId);
        }

        if ($this->_returnPath[$storeId]) {
            $message->setReturnPath($this->_returnPath[$storeId]);
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
            $this->_developerMode[$storeId] = $this->smtpHelper->getDeveloperConfig('developer_mode', $storeId);
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
            $this->_emailLog[$storeId] = $this->smtpHelper->getConfigGeneral('log_email', $storeId);
        }

        return $this->_emailLog[$storeId];
    }

}
