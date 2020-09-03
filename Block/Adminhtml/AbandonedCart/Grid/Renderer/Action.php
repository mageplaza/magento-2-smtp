<?php

namespace Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Grid\Renderer;

use Magento\Framework\DataObject;

/**
 * Sitemap grid action column renderer
 */
class Action extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\Action
{
    /**
     * @param DataObject $row
     * @return string
     */
    public function render(DataObject $row)
    {
        $this->getColumn()->setActions(
            [
                [
                    'url' => $this->getUrl('adminhtml/smtp_abandonedcart/view', ['id' => $row->getQuoteId()]),
                    'caption' => __('View'),
                ],
            ]
        );
        return parent::render($row);
    }
}
