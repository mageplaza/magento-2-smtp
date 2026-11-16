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

namespace Mageplaza\Smtp\Model\AdminNotification;

use Magento\Framework\Notification\MessageInterface;
use Mageplaza\Smtp\Model\FailureState;

/**
 * Critical admin system message shown while SMTP sending is failing.
 * It is removed automatically once a later send for that store view succeeds.
 *
 * Class SmtpFailureMessage
 * @package Mageplaza\Smtp\Model\AdminNotification
 */
class SmtpFailureMessage implements MessageInterface
{
    /**
     * @var FailureState
     */
    protected $failureState;

    /**
     * @param FailureState $failureState
     */
    public function __construct(FailureState $failureState)
    {
        $this->failureState = $failureState;
    }

    /**
     * @return bool
     */
    public function isDisplayed()
    {
        return $this->failureState->isFailing();
    }

    /**
     * @return string
     */
    public function getIdentity()
    {
        return 'mageplaza_smtp_send_failure';
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return $this->failureState->getNoticeText();
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }
}
