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

namespace Mageplaza\Smtp\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Core\Helper\AbstractData;

/**
 * Class Data
 * @package Mageplaza\Smtp\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH     = 'smtp';
    const EMAIL_MARKETING        = 'email_marketing';
    const CONFIG_GROUP_SMTP      = 'configuration_option';
    const DEVELOP_GROUP_SMTP     = 'developer';
    const OAUTH_CACHE_KEY_PREFIX = 'mp_smtp_oauth2_token_';

    /** @var Curl */
    protected $curl;

    /** @var CacheInterface */
    protected $cache;

    /** @var Json */
    protected $json;

    /**
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param Curl $curl
     * @param CacheInterface $cache
     * @param Json $json
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Curl $curl,
        CacheInterface $cache,
        Json $json
    ) {
        parent::__construct($context, $objectManager, $storeManager);
        $this->curl  = $curl;
        $this->cache = $cache;
        $this->json  = $json;
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return mixed
     */
    public function getSmtpConfig($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getModuleConfig(self::CONFIG_GROUP_SMTP . $code, $storeId);
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return mixed
     */
    public function getDeveloperConfig($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getModuleConfig(self::DEVELOP_GROUP_SMTP . $code, $storeId);
    }

    /**
     * @param null $storeId
     * @param bool $decrypt
     *
     * @return array|mixed|string
     */
    public function getPassword($storeId = null, $decrypt = true)
    {
        if ($storeId || $storeId = $this->_request->getParam('store')) {
            $password = $this->getSmtpConfig('password', $storeId);
        } elseif ($websiteCode = $this->_request->getParam('website')) {
            $passwordPath = self::CONFIG_MODULE_PATH . '/' . self::CONFIG_GROUP_SMTP . '/password';
            $password     = $this->getConfigValue($passwordPath, $websiteCode, ScopeInterface::SCOPE_WEBSITE);
        } else {
            $password = $this->getSmtpConfig('password');
        }

        if ($decrypt) {
            /** @var EncryptorInterface $encryptor */
            $encryptor = $this->getObject(EncryptorInterface::class);

            return $encryptor->decrypt($password);
        }

        return $password;
    }

    /**
     * @return int
     * @throws LocalizedException
     */
    public function getScopeId()
    {
        $scope = $this->_request->getParam(ScopeInterface::SCOPE_STORE) ?: $this->storeManager->getStore()->getId();

        if ($website = $this->_request->getParam(ScopeInterface::SCOPE_WEBSITE)) {
            $scope = $this->storeManager->getWebsite($website)->getDefaultStore()->getId();
        }

        return $scope;
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getBlacklist($storeId = null)
    {
        return $this->getConfigGeneral('blacklist', $storeId);
    }

    /**
     * @return bool
     */
    public function isTestEmail()
    {
        return $this->_request->getFullActionName() === 'adminhtml_smtp_test';
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return mixed
     */
    public function getEmailMarketingConfig($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getConfigValue(static::EMAIL_MARKETING . '/general' . $code, $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isEnableEmailMarketing($storeId = null)
    {
        return $this->getEmailMarketingConfig('enabled', $storeId);
    }

    /**
     * @param $key
     * @param $storeId
     *
     * @return array|mixed
     */
    public function getOauthConfig($key = '', $storeId = null)
    {
        $code = $key !== '' ? '/' . $key : '';

        return $this->getModuleConfig(self::CONFIG_GROUP_SMTP . $code, $storeId);
    }

    /**
     * @param $storeId
     * @param $configOverride
     *
     * @return mixed|string|null
     * @throws LocalizedException
     */
    public function getOauthAccessToken($storeId = null, $configOverride = null)
    {
        $config   = $configOverride ?: $this->getSmtpConfig('', $storeId);
        $authType = $config['authentication'] ?? ($config['auth'] ?? '');

        if ($authType !== 'oauth2') {
            return null;
        }

        $tenantId = trim($config['oauth_tenant_id'] ?? '');
        $clientId = trim($config['oauth_client_id'] ?? '');

        if (isset($config['oauth_client_secret']) && $config['oauth_client_secret']) {
            $clientSecret = $config['oauth_client_secret'];

            if (str_contains($clientSecret, ':')) {
                try {
                    /** @var EncryptorInterface $encryptor */
                    $encryptor    = $this->getObject(EncryptorInterface::class);
                    $clientSecret = $encryptor->decrypt($clientSecret);
                } catch (\Exception $e) {
                    $this->_logger->critical($e->getMessage());
                }
            }
        } else {
            $clientSecret = $this->getEncryptedConfigValue('oauth_client_secret', $storeId);
        }

        // Use Microsoft Graph API scope for sendMail API
        $scope = trim($config['oauth_scope'] ?? 'https://graph.microsoft.com/.default');

        if (!$tenantId || !$clientId || !$clientSecret) {
            // Add more detailed error message
            $missing = [];
            if (!$tenantId) {
                $missing[] = 'Tenant ID';
            }
            if (!$clientId) {
                $missing[] = 'Client ID';
            }
            if (!$clientSecret) {
                $missing[] = 'Client Secret';
            }

            throw new LocalizedException(
                __('OAuth2 is selected but the following are missing: %1', implode(', ', $missing))
            );
        }

        $cacheKey = self::OAUTH_CACHE_KEY_PREFIX . $storeId . '_' . hash('sha256', $tenantId . $clientId . $scope);
        if ($token = $this->cache->load($cacheKey)) {
            return $token;
        }

        $url    = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId);
        $params = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => $scope,
        ]);

        $this->curl->setOption(CURLOPT_TIMEOUT, 15);
        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->post($url, $params);

        $statusCode = $this->curl->getStatus();
        $body       = $this->curl->getBody();

        if ($statusCode !== 200) {
            throw new LocalizedException(__('OAuth token request failed (HTTP %1): %2', $statusCode, $body));
        }

        $data = $this->json->unserialize($body);
        if (empty($data['access_token'])) {
            throw new LocalizedException(__('OAuth token response missing access_token.'));
        }

        $token = $data['access_token'];
        $this->cache->save($token, $cacheKey, [], $this->getOauthTokenLifetime($data));

        return $token;
    }

    /**
     * @param array $data Token endpoint response
     *
     * @return int
     */
    public function getOauthTokenLifetime($data)
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;

        if ($expiresIn <= 0) {
            $parts   = explode('.', (string) $data['access_token']);
            $payload = isset($parts[1])
                ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true)
                : null;

            if (!empty($payload['exp'])) {
                $expiresIn = (int) $payload['exp'] - time();
            }
        }

        return $expiresIn > 0 ? max(60, $expiresIn - 120) : 3300;
    }

    /**
     * Check if we should use Microsoft Graph API for sending emails
     *
     * @param null $storeId
     * @param null $configOverride
     *
     * @return bool
     */
    public function shouldUseGraphApi($storeId = null, $configOverride = null)
    {
        $config = $configOverride ?: $this->getSmtpConfig('', $storeId);
        $auth   = $config['authentication'] ?? ($config['auth'] ?? '');

        return ($auth === 'oauth2');
    }

    /**
     * @param $field
     * @param $storeId
     *
     * @return string
     */
    private function getEncryptedConfigValue($field, $storeId = null)
    {
        $path      = self::CONFIG_MODULE_PATH . '/' . self::CONFIG_GROUP_SMTP . '/' . $field;
        $encrypted = $this->getConfigValue($path, $storeId);
        if (!$encrypted) {
            return '';
        }
        /** @var EncryptorInterface $encryptor */
        $encryptor = $this->getObject(EncryptorInterface::class);

        return $encryptor->decrypt($encrypted);
    }
}
