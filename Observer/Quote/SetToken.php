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

namespace Mageplaza\Smtp\Observer\Quote;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Quote\Model\Quote;

/**
 * Class SetToken
 * @package Mageplaza\Smtp\Observer\Quote
 */
class SetToken implements ObserverInterface
{
    /**
     * @var Random
     */
    protected $random;

    /**
     * SetToken constructor.
     *
     * @param Random $random
     */
    public function __construct(
        Random $random
    ) {
        $this->random = $random;
    }

    /**
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /* @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote->getData('mp_smtp_ace_token')) {
            $quote->setData('mp_smtp_ace_token', $this->random->getUniqueHash());
        }
    }
}
