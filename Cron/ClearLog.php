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

/**
 * Class ClearLog
 * @package Mageplaza\Smtp\Cron
 */
class ClearLog
{
	const CLEANER_GROUP_SMTP = 'cleaner';
	const GENERAL_GROUP_SMTP = 'general';

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var \Mageplaza\Smtp\Helper\Data
	 */
	private $smtpDataHelper;

	/**
	 * @var \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory
	 */
	private $collectionLog;

	/**
	 * @var \Magento\Framework\Stdlib\DateTime\DateTime
	 */
	private $date;

	/**
	 * Constructor
	 *
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
	 * @param \Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory $collectionLog
	 * @param \Mageplaza\Smtp\Helper\Data $smtpDataHelper
	 */
	public function __construct(
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Stdlib\DateTime\DateTime $date,
		\Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory $collectionLog,
		\Mageplaza\Smtp\Helper\Data $smtpDataHelper
	)
	{
		$this->logger         = $logger;
		$this->date           = $date;
		$this->collectionLog  = $collectionLog;
		$this->smtpDataHelper = $smtpDataHelper;
	}

	/**
	 * Clean Email Log after X day(s)
	 *
	 * @return void
	 */
	public function execute()
	{
		$day = (int)$this->smtpDataHelper->getConfig(self::CLEANER_GROUP_SMTP, 'clean_email');
		if (isset($day) && $day > 0 && $this->smtpDataHelper->getConfig(self::GENERAL_GROUP_SMTP, 'enabled')) {
			$timeEnd = strtotime($this->date->date()) - $day * 24 * 60 * 60;
			$logs    = $this->collectionLog->create()->addFieldToFilter('created_at', ['lteq' => date('Y-m-d H:i:s', $timeEnd)]);
			try {
				foreach ($logs as $log) {
					$log->delete();
				}
			} catch (\Exception $e) {
				$this->logger->critical($e);
			}
		}
	}
}
