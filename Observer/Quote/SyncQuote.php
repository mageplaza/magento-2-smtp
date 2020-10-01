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

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\Smtp\Helper\AbandonedCart;
use Psr\Log\LoggerInterface;

/**
 * Class SyncQuote
 * @package Mageplaza\Smtp\Observer\Quote
 */
class SyncQuote implements ObserverInterface
{
    /**
     * @var AbandonedCart
     */
    protected $helperAbandonedCart;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerSaveCommitAfter constructor.
     * @param AbandonedCart $helperAbandonedCart
     * @param LoggerInterface $logger
     */
    public function __construct(
        AbandonedCart $helperAbandonedCart,
        LoggerInterface $logger
    ) {
        $this->helperAbandonedCart = $helperAbandonedCart;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {

        if ($this->helperAbandonedCart->isEnableAbandonedCart() &&
            $this->helperAbandonedCart->getSecretKey() &&
            $this->helperAbandonedCart->getAppID()
        ) {
            try {
                /* @var Quote $quote */
                $quote = $observer->getEvent()->getQuote();
                if ($quote->getId()) {

                    $ACEData = $this->helperAbandonedCart->getACEData($quote);
                    $oldACEData = $quote->getData('mp_smtp_ace_log_data') ?
                        AbandonedCart::jsonDecode($quote->getData('mp_smtp_ace_log_data')) : [];
                    if ($oldACEData !== $ACEData && empty($oldACEData['checkoutCompleted'])) {
                        $resource = $this->helperAbandonedCart->getResourceQuote();
                        $resource->getConnection()->update(
                            $resource->getMainTable(),
                            ['mp_smtp_ace_log_data' => AbandonedCart::jsonEncode($ACEData)],
                            ['entity_id = ?' => $quote->getId()]
                        );

                        $this->helperAbandonedCart->sendRequestWithoutWaitResponse($ACEData);
                    }
                }
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
    }
}
