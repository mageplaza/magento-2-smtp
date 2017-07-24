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

namespace Mageplaza\Smtp\Model\ResourceModel;

/**
 * Class Log
 * @package Mageplaza\Smtp\Model\ResourceModel
 */
class Log extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
	/**
	 * Date model
	 *
	 * @var \Magento\Framework\Stdlib\DateTime\DateTime
	 */
	private $date;

	/**
	 * constructor
	 *
	 * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
	 * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
	 */
	public function __construct(
		\Magento\Framework\Stdlib\DateTime\DateTime $date,
		\Magento\Framework\Model\ResourceModel\Db\Context $context
	)
	{
		$this->date = $date;
		parent::__construct($context);
	}


	/**
	 * Initialize resource model
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('mageplaza_smtp_log', 'id');
	}

	/**
	 * before save callback
	 *
	 * @param \Magento\Framework\Model\AbstractModel|\Mageplaza\Smtp\Model\Log $object
	 * @return $this
	 */
	protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
	{
		if ($object->isObjectNew()) {
			$object->setCreatedAt($this->date->date());
		}

		return parent::_beforeSave($object);
	}
}
