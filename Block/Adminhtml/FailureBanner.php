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

namespace Mageplaza\Smtp\Block\Adminhtml;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Smtp\Model\FailureState;

/**
 * Sticky SMTP failure banner rendered on every admin page.
 *
 * The collapsible System Messages widget hides critical notices behind a click
 * and does not reliably surface them, so this banner stays visible until
 * sending recovers.
 *
 * Class FailureBanner
 * @package Mageplaza\Smtp\Block\Adminhtml
 */
class FailureBanner extends Template
{
    /**
     * @var FailureState
     */
    protected $failureState;

    /**
     * @param Context $context
     * @param FailureState $failureState
     * @param array $data
     */
    public function __construct(
        Context $context,
        FailureState $failureState,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->failureState = $failureState;
    }

    /**
     * @return bool
     */
    public function isFailing()
    {
        return $this->failureState->isFailing();
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getNoticeText()
    {
        return $this->failureState->getNoticeText();
    }

    /**
     * @return string
     */
    public function getConfigUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'smtp']);
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->isFailing()) {
            return '';
        }

        return parent::_toHtml();
    }
}
