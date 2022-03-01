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

use Exception;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\Smtp\Helper\Data;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Class ClearLog
 * @package Mageplaza\Smtp\Helper
 */
class ClearLog
{
    const LOG_TABLE = 'mageplaza_smtp_log';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * DB connection.
     *
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * ClearLog constructor.
     *
     * @param LoggerInterface $logger
     * @param DateTime $date
     * @param ResourceConnection $connection
     * @param Data $helper
     */
    public function __construct(
        LoggerInterface $logger,
        DateTime $date,
        ResourceConnection $connection,
        Data $helper
    )
    {
        $this->logger = $logger;
        $this->date = $date;
        $this->connection = $connection;
        $this->helper = $helper;
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

        $day = (int)$this->helper->getConfigGeneral('clean_email');
        if (isset($day) && $day > 0) {
            try {
                $timeEnd = strtotime($this->date->date()) - $day * 24 * 60 * 60;
                $date = date('Y-m-d H:i:s', $timeEnd);

                $connection = $this->connection->getConnection();
                $tableName = $connection->getTableName(self::LOG_TABLE);

                $whereConditions = [
                    $connection->quoteInto('created_at <= ?', $date),
                ];

                $deleteRows = $connection->delete($tableName, $whereConditions);
            } catch (Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $this;
    }
}
