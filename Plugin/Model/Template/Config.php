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

namespace Mageplaza\Smtp\Plugin\Model\Template;

use Magento\Email\Model\Template\Config as EmailConfig;

/**
 * Class Config
 * @package Mageplaza\Smtp\Plugin\Model\Template
 */
class Config
{
    /**
     * @param EmailConfig $subject
     * @param array $templates
     *
     * @return array
     */
    public function afterGetAvailableTemplates(EmailConfig $subject, $templates)
    {
        $key = array_search('mpsmtp_abandoned_cart_email_templates', array_column($templates, 'value'));
        unset($templates[$key]);

        return $templates;
    }
}
