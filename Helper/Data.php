<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Helper;

use Magento\Framework\App\Area;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\Core\Helper\AbstractData;

/**
 * Class Data
 * @package Mageplaza\Smtp\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'smtp';
    const CONFIG_GROUP_SMTP  = 'configuration_option';
    const DEVELOP_GROUP_SMTP = 'developer';

    /**
     * @var \Magento\Backend\App\Config
     */
    protected $backendConfig;

    /**
     * @var bool
     */
    protected $isFrontendArea;

    /**
     * @param $group
     * @param $code
     * @param null $storeId
     * @return mixed
     */
    public function getConfig($group, $code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getConfigValue(static::CONFIG_MODULE_PATH . '/' . $group . $code, $storeId);
    }

    /**
     * Will be removed when module Core is updated
     *
     * @param $field
     * @param null $scopeValue
     * @param string $scopeType
     * @return array|mixed
     */
    public function getConfigValueTmp($field, $scopeValue = null, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        if (!$this->isFrontend() && is_null($scopeValue)) {
            /** @var \Magento\Backend\App\Config $backendConfig */
            if (!$this->backendConfig) {
                $this->backendConfig = $this->objectManager->get('Magento\Backend\App\ConfigInterface');
            }

            return $this->backendConfig->getValue($field);
        }

        return $this->scopeConfig->getValue($field, $scopeType, $scopeValue);
    }

    /**
     * Will be removed when module Core is updated
     *
     * Is Admin Store
     *
     * @return bool
     */
    public function isFrontend()
    {
        if (!isset($this->isFrontendArea)) {
            /** @var \Magento\Framework\App\State $state */
            $state = $this->objectManager->get('Magento\Framework\App\State');

            try {
                $areaCode = $state->getAreaCode();

                $this->isFrontendArea = ($areaCode == Area::AREA_FRONTEND);
            } catch (\Exception $e) {
                $this->isFrontendArea = false;
            }
        }

        return $this->isFrontendArea;
    }

    /**
     * @return array|mixed
     */
    public function getTestPassword()
    {
        $passwordPath = self::CONFIG_MODULE_PATH . '/' . self::CONFIG_GROUP_SMTP . '/password';
        $websiteCode  = $this->_request->getParam('website');
        $storeCode    = $this->_request->getParam('store');

        if (!$storeCode && $websiteCode) {
            $password = $this->getConfigValueTmp($passwordPath, $websiteCode, ScopeInterface::SCOPE_WEBSITE);
        } else if ($storeCode) {
            $password = $this->getConfigValueTmp($passwordPath, $storeCode);
        } else {
            $password = $this->getConfigValueTmp($passwordPath);
        }

        return $password;
    }
}
