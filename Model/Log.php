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

namespace Mageplaza\Smtp\Model;

/**
 * Class Log
 * @package Mageplaza\Smtp\Model
 */
class Log extends \Magento\Framework\Model\AbstractModel
{
	/**
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('Mageplaza\Smtp\Model\ResourceModel\Log');
	}

	/**
	 * Save emails log
	 *
	 * @return void
	 */
	public function saveLog($message, $status)
	{
		if ($message) {
			$headers = $message->getHeaders();
			$subject = $headers['Subject'][0];
			$content = htmlspecialchars($message->getBodyHtml()->getRawContent());
			if ($subject) {
				$this->setSubject($subject);
			}
			$this->setEmailContent($content)
				->setStatus($status)
				->save();
		}
	}
}
