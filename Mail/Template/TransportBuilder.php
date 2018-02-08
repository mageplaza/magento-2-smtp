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

namespace Mageplaza\Smtp\Mail\Template;

/**
 * Class TransportBuilder
 * @package Mageplaza\Smtp\Mail\Template
 */
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
	/**
	 * Get mail transport
	 *
	 * @return \Magento\Framework\Mail\TransportInterface
	 */
	public function getTransport()
	{
		$transport = parent::getTransport();

		if (isset($this->templateOptions['store']) && method_exists($transport, 'setStoreId')) {
			$transport->setStoreId($this->templateOptions['store']);
		}

		return $transport;
	}

	/**
	 * @param $content
	 * @param string $mimeType
	 * @param string $disposition
	 * @param string $encoding
	 * @param string $filename
	 * @return $this
	 */
	public function addAttachment(
		$content,
		$mimeType = \Zend_Mime::TYPE_OCTETSTREAM,
		$disposition = \Zend_Mime::DISPOSITION_ATTACHMENT,
		$encoding = \Zend_Mime::ENCODING_BASE64,
		$filename = 'mageplaza.pdf'
	)
	{
		$this->message->createAttachment(
			$content,
			$mimeType,
			$disposition,
			$encoding,
			$filename
		);

		return $this;
	}

}
