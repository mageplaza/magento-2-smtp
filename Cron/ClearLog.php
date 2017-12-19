<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) 2017 Mageplaza (https://www.mageplaza.com/)
 * @license     http://mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class ClearLog
 * @package Mageplaza\Smtp\Cron
 */
class ClearLog
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Mageplaza\Smtp\Helper\Data
     */
    protected $helper;

    /**
     * @var \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory
     */
    protected $collectionLog;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * ClearLog constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory $collectionLog
     * @param \Mageplaza\Smtp\Helper\Data $helper
     */
    public function __construct(
        LoggerInterface $logger,
        DateTime $date,
        CollectionFactory $collectionLog,
        Data $helper
    )
    {
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

        $day = (int)$this->helper->getConfig(Data::DEVELOP_GROUP_SMTP, 'clean_email');
        if (isset($day) && $day > 0) {
            $timeEnd = strtotime($this->date->date()) - $day * 24 * 60 * 60;
            $logs    = $this->collectionLog->create()
                ->addFieldToFilter('created_at', ['lteq' => date('Y-m-d H:i:s', $timeEnd)]);
            try {
                foreach ($logs as $log) {
                    $log->delete();
                }
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $this;
    }
}
