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

namespace Mageplaza\Smtp\Cron;

use Exception;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class ClearLog
 * @package Mageplaza\Smtp\Cron
 */
class ClearLog
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CollectionFactory
     */
    protected $collectionLog;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * ClearLog constructor.
     *
     * @param LoggerInterface $logger
     * @param DateTime $date
     * @param CollectionFactory $collectionLog
     * @param Data $helper
     */
    public function __construct(
        LoggerInterface $logger,
        DateTime $date,
        CollectionFactory $collectionLog,
        Data $helper
    ) {
        $this->logger        = $logger;
        $this->date          = $date;
        $this->collectionLog = $collectionLog;
        $this->helper        = $helper;
    }

    /**
     * Clean Email Log after X day(s)
     *
     * @return $this
     */
    public function execute()
    {
        if (!$this->helper->isEnabled()) {
            return $this;
        }

        $day = (int) $this->helper->getConfigGeneral('clean_email');
        if (isset($day) && $day > 0) {
            $timeEnd = strtotime($this->date->date()) - $day * 24 * 60 * 60;

            /** @var Collection $logs */
            $logs = $this->collectionLog->create()
                ->addFieldToFilter('created_at', ['lteq' => date('Y-m-d H:i:s', $timeEnd)]);

            /**
             * We've stumbled into a case where the module would run out of memory if the emails sent were too many like (400k) in our case. By the time the cleanup cron tried to clean them, it would run out of memory.
             * I've used collection pagination to reduce the strain of this cleanup cron job. #394
             */
            $logs->setPageSize(100);
            $pages = $logs->getLastPageNumber();
            for ($pageNum = 1; $pageNum<=$pages; $pageNum++) {
                $logs->setCurPage($pageNum);
                foreach ($logs as $log) {
                    try {
                        $log->delete();
                    } catch (Exception $e) {
                        $this->logger->critical($e);
                    }
                }
                $logs->clear();
            }
        }

        return $this;
    }
}
