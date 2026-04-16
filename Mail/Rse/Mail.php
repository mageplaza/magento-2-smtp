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

use Mageplaza\Smtp\Helper\Data;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Parser\MessageParser;
use Zend_Exception;

/**
 * Class Mail
 * @package Mageplaza\Smtp\Application\Rse
 */
class Mail
{
    /**
     * @var Data
     */
    protected $smtpHelper;

    /**
     * @var array Is module enable by store
     */
    protected $_moduleEnable = [];

    /**
     * @var array is developer mode
     */
    protected $_developerMode = [];

    /**
     * @var array is enable email log
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
     * @var Smtp
     */
    protected $_transport;

    /**
     * @var array
     */
    protected $_fromByStore = [];

    /**
     * Mail constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->smtpHelper = $helper;
    }

    /**
     * @param $storeId
     * @param array $options
     *
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

        if (count($options)) {
            $this->_smtpOptions[$storeId] = $options;
        }

        return $this;
    }

    /**
     * @param $storeId
     *
     * @return Smtp
     * @throws Zend_Exception
     */
    public function getTransport($storeId)
    {
        if ($this->_transport === null) {
            if (!isset($this->_smtpOptions[$storeId])) {
                $configData = $this->smtpHelper->getSmtpConfig('', $storeId);
                $options    = [
                    'host' => isset($configData['host']) ? $configData['host'] : '',
                    'port' => isset($configData['port']) ? $configData['port'] : ''
                ];

                if (isset($configData['authentication']) && $configData['authentication'] !== "") {
                    $options += [
                        'auth'     => $configData['authentication'],
                        'username' => isset($configData['username']) ? $configData['username'] : '',
                        'password' => $this->smtpHelper->getPassword($storeId)
                    ];
                }

                if (isset($configData['protocol']) && $configData['protocol'] !== "") {
                    $options['ssl'] = $configData['protocol'];
                }

                $this->_smtpOptions[$storeId] = $options;
            }

            if (!isset($this->_smtpOptions[$storeId]['host']) || !$this->_smtpOptions[$storeId]['host']) {
                throw new Zend_Exception(__('A host is necessary for smtp transport, but none was given'));
            }

            if ($this->smtpHelper->versionCompare('2.2.8')) {
                $options = $this->_smtpOptions[$storeId];
                if (isset($options['auth'])) {
                    $options['connection_class']  = $options['auth'];
                    $options['connection_config'] = [
                        'username' => $options['username'],
                        'password' => $options['password']
                    ];
                    unset($options['auth'], $options['username'], $options['password']);
                }
                if (isset($options['ssl'])) {
                    $options['connection_config']['ssl'] = $options['ssl'];
                    unset($options['ssl']);
                }
                unset($options['type']);

                $options = new SmtpOptions($options);

                $this->_transport = new Smtp($options);
            } else {
                $this->_transport = new Smtp();
            }
        }

        return $this->_transport;
    }

    /**
     * @param $message
     * @param $storeId
     *
     * @return mixed
     */
    public function processMessage($message, $storeId)
    {
        if (!isset($this->_returnPath[$storeId])) {
            $this->_returnPath[$storeId] = $this->smtpHelper->getSmtpConfig('return_path_email', $storeId);
        }

        if ($this->_returnPath[$storeId]) {
            if ($this->smtpHelper->versionCompare('2.2.8')) {
                $message->getHeaders()->addHeaders(["Return-Path" => $this->_returnPath[$storeId]]);
            } elseif (method_exists($message, 'setReturnPath')) {
                $message->setReturnPath($this->_returnPath[$storeId]);
            }
        }

        if (!empty($this->_fromByStore) &&
            ((is_array($message->getHeaders()) && !array_key_exists("From", $message->getHeaders())) ||
                ($message instanceof Message && !$message->getFrom()->count()))
        ) {
            $message->setFrom($this->_fromByStore['email'], $this->_fromByStore['name']);
        }

        return $message;
    }

    /**
     * @param $email
     * @param $name
     *
     * @return $this
     */
    public function setFromByStore($email, $name)
    {
        $this->_fromByStore = [
            'email' => $email,
            'name'  => $name
        ];

        return $this;
    }

    /**
     * @param $storeId
     *
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
     *
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
     * Get SMTP options for a store (includes temporary test options)
     *
     * @param $storeId
     * @return array|null
     */
    public function getSmtpOptions($storeId)
    {
        if (isset($this->_smtpOptions[$storeId])) {
            return $this->_smtpOptions[$storeId];
        }

        return null;
    }

    /**
     * @param $storeId
     *
     * @return bool|mixed
     */
    public function isEnableEmailLog($storeId)
    {
        if (!isset($this->_emailLog[$storeId])) {
            $this->_emailLog[$storeId] = $this->smtpHelper->getConfigGeneral('log_email', $storeId);
        }

        return $this->_emailLog[$storeId];
    }

    /**
     * @param string $storeId
     *
     * @return EsmtpTransport
     */
    public function getSymfonyTransport($storeId)
    {
        $config   = $this->smtpHelper->getSmtpConfig('', $storeId);
        $host     = $config['host'] ?? 'localhost';
        $port     = (int) ($config['port'] ?? 25);
        $protocol = $config['protocol'] ?? null; // tls | ssl | null
        $username = $config['username'] ?? null;
        $password = $this->smtpHelper->getPassword($storeId);

        // CRITICAL: Strip ssl:// or tls:// prefix from host - Symfony adds it automatically
        $host = preg_replace('#^(ssl|tls)://#i', '', $host);

        // Symfony $tls logic:
        // - true  → adds ssl:// prefix (implicit SSL, for port 465 only)
        // - false → plain connection, but EsmtpTransport auto-negotiates STARTTLS if available
        // - null  → auto-detect based on port (465 → true, others → false)

        $tls = false; // default: plain connection with auto STARTTLS negotiation

        if ($protocol === 'ssl' || $port === 465) {
            // Implicit SSL: requires ssl:// prefix from start (Symfony adds it when $tls=true)
            $tls = true;
            if ($port !== 465) {
                $port = 465; // Force port 465 for implicit SSL
            }
        } elseif ($protocol === 'tls') {
            // STARTTLS: plain connection first, then upgrade via STARTTLS command
            // Do NOT use $tls=true here, it would add ssl:// which breaks STARTTLS
            $tls = false; // Let Symfony negotiate STARTTLS automatically
            if ($port === 465) {
                $port = 587; // STARTTLS uses 587, not 465
            }
        }

        $transport = new EsmtpTransport($host, $port, $tls);

        if ($username && $password) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        return $transport;
    }
}
