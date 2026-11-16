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

namespace Mageplaza\Smtp\Model;

use Magento\Framework\FlagManager;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Persists SMTP send failures in the core flag table so the admin notice
 * survives requests and cache flushes until that store view sends mail again.
 *
 * State is kept per store view. A later success on one view does not hide
 * an ongoing failure on another.
 *
 * Class FailureState
 * @package Mageplaza\Smtp\Model
 */
class FailureState
{
    private const FLAG_CODE = 'mageplaza_smtp_send_failure';

    /**
     * @var FlagManager
     */
    protected $flagManager;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @param FlagManager $flagManager
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        FlagManager $flagManager,
        TimezoneInterface $timezone
    ) {
        $this->flagManager = $flagManager;
        $this->timezone    = $timezone;
    }

    /**
     * Remember a failed send, keeping the original failure time for this store view.
     *
     * @param int|string|null $storeId
     * @param string $message Redacted error text safe to show in the admin.
     *
     * @return void
     */
    public function recordFailure($storeId, $message)
    {
        $stores = $this->getStores();
        $key    = $this->storeKey($storeId);
        $now    = $this->timezone->date()->format('Y-m-d H:i:s');
        $current = $stores[$key] ?? [];

        $stores[$key] = [
            'message'         => (string) $message,
            'first_failed_at' => $current['first_failed_at'] ?? $now,
            'last_failed_at'  => $now,
            'count'           => (int) ($current['count'] ?? 0) + 1,
        ];

        $this->flagManager->saveFlag(self::FLAG_CODE, ['stores' => $stores]);
    }

    /**
     * Drop the failure for a store view after a message is sent successfully.
     *
     * @param int|string|null $storeId
     *
     * @return void
     */
    public function clear($storeId)
    {
        $stores = $this->getStores();
        $key    = $this->storeKey($storeId);

        if (!isset($stores[$key])) {
            return;
        }

        unset($stores[$key]);

        if ($stores === []) {
            $this->flagManager->deleteFlag(self::FLAG_CODE);

            return;
        }

        $this->flagManager->saveFlag(self::FLAG_CODE, ['stores' => $stores]);
    }

    /**
     * @return bool
     */
    public function isFailing()
    {
        return $this->getStores() !== [];
    }

    /**
     * Message from the most recent failure.
     *
     * @return string
     */
    public function getMessage()
    {
        $latest = null;

        foreach ($this->getStores() as $store) {
            if ($latest === null || strcmp((string) $store['last_failed_at'], (string) $latest['last_failed_at']) > 0) {
                $latest = $store;
            }
        }

        return (string) ($latest['message'] ?? '');
    }

    /**
     * Earliest failure time across store views that are still failing.
     *
     * @return string
     */
    public function getFirstFailedAt()
    {
        $first = '';

        foreach ($this->getStores() as $store) {
            $candidate = (string) ($store['first_failed_at'] ?? '');
            if ($candidate !== '' && ($first === '' || strcmp($candidate, $first) < 0)) {
                $first = $candidate;
            }
        }

        return $first;
    }

    /**
     * @return int
     */
    public function getFailureCount()
    {
        $count = 0;

        foreach ($this->getStores() as $store) {
            $count += (int) ($store['count'] ?? 0);
        }

        return $count;
    }

    /**
     * Store view ids that still have a recorded failure.
     *
     * @return string[]
     */
    public function getStoreIds()
    {
        return array_map('strval', array_keys($this->getStores()));
    }

    /**
     * Admin-facing summary. The error text is expected to already be redacted.
     *
     * @return Phrase
     */
    public function getNoticeText()
    {
        $storeIds = $this->getStoreIds();
        $scope    = $storeIds === []
            ? ''
            : ' ' . __('Affected store view ID(s): %1.', implode(', ', $storeIds));

        return __(
            'Store emails have been failing to send since %1 (%2 failed attempt(s)). '
            . 'Customers may not be receiving order, account, or password emails. '
            . 'Review the configuration under Stores > SMTP > Configuration. Last error: %3%4',
            $this->getFirstFailedAt(),
            $this->getFailureCount(),
            $this->getMessage(),
            $scope
        );
    }

    /**
     * @return array
     */
    private function getStores()
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);

        if (!is_array($data) || empty($data['stores']) || !is_array($data['stores'])) {
            return [];
        }

        return $data['stores'];
    }

    /**
     * @param int|string|null $storeId
     *
     * @return string
     */
    private function storeKey($storeId)
    {
        if ($storeId === null || $storeId === '') {
            return '0';
        }

        return (string) $storeId;
    }
}
