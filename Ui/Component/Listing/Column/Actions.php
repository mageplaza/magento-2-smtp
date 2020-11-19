<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Smtp\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\Smtp\Helper\Data;

/**
 * Class Actions
 * @package Mageplaza\Smtp\Ui\Component\Listing\Column
 */
class Actions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Actions constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Data $helperData
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Data $helperData,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);

        $this->helperData = $helperData;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = [
                    'view'   => [
                        'label' => __('View')
                    ],
                    'resend' => [
                        'href'    => $this->urlBuilder->getUrl('adminhtml/smtp/email', ['id' => $item['id']]),
                        'label'   => __('Resend'),
                        'confirm' => [
                            'title'   => __('Resend Email'),
                            'message' => __(
                                'Are you sure you want to resend the email <strong>"%1"</strong>?',
                                $item['subject']
                            )
                        ]
                    ],
                    'delete' => [
                        'href'    => $this->urlBuilder->getUrl('adminhtml/smtp/delete', ['id' => $item['id']]),
                        'label'   => __('Delete'),
                        'confirm' => [
                            'title'   => __('Delete Log'),
                            'message' => __('Are you sure you want to delete this log?')
                        ]
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
