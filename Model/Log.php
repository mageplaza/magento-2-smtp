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

use Magento\Framework\Model\AbstractModel;

/**
 * Class Log
 * @package Mageplaza\Smtp\Model
 */
class Log extends AbstractModel
{
	/**
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('Mageplaza\Smtp\Model\ResourceModel\Log');
	}

    /**
     * Save email logs
     *
     * @param $message
     * @param $status
     */
	public function saveLog($message, $status)
	{
		if ($message) {
			$headers = $message->getHeaders();

			if (isset($headers['Subject']) && isset($headers['Subject'][0])) {
				$this->setSubject($headers['Subject'][0]);
			}

            if (isset($headers['From']) && isset($headers['From'][0])) {
                $this->setFrom($headers['From'][0]);
            }

            if(isset($headers['To'])){
			    $recipient = $headers['To'];
			    if(isset($recipient['append'])){
			        unset($recipient['append']);
                }
			    $this->setTo(implode(', ', $recipient));
            }

            if(isset($headers['Cc'])){
                $cc = $headers['Cc'];
                if(isset($cc['append'])){
                    unset($cc['append']);
                }
                $this->setCc(implode(', ', $cc));
            }

            if(isset($headers['Bcc'])){
                $bcc = $headers['Bcc'];
                if(isset($bcc['append'])){
                    unset($bcc['append']);
                }
                $this->setBcc(implode(', ', $bcc));
            }

            $body = $message->getBodyHtml();
            if (is_object($body)) {
                $content = htmlspecialchars($body->getRawContent());
            } else {
                $content = htmlspecialchars($message->getBody()->getRawContent());
            }

			$this->setEmailContent($content)
				->setStatus($status)
				->save();
		}
	}
}
