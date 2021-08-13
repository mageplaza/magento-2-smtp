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

namespace Mageplaza\Smtp\Plugin;

use Magento\Framework\Exception\MailException;
use Magento\Eav\Model\Entity\AbstractEntity;

/**
 * Class DefaultAttributes
 * @package Mageplaza\Smtp\Plugin
 */
class DefaultAttributes
{
    /**
     * @param AbstractEntity $subject
     * @param $result
     * @return array
     */
    public function afterGetDefaultAttributes(AbstractEntity $subject, $result)
    {
        $entity = [
            'mp_smtp_email_marketing_synced'
        ];

        return array_merge($result, $entity);
    }
}
