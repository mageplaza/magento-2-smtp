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

namespace Mageplaza\Smtp\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\Smtp\Helper\EmailMarketing;

/**
 * Class CustomerName
 * @package Mageplaza\Smtp\Ui\Component\Listing\Column
 */
class CustomerName extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * CustomerName constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param QuoteFactory $quoteFactory
     * @param EmailMarketing $helperEmailMarketing
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        QuoteFactory $quoteFactory,
        EmailMarketing $helperEmailMarketing,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);

        $this->urlBuilder = $urlBuilder;
        $this->quoteFactory = $quoteFactory;
        $this->helperEmailMarketing = $helperEmailMarketing;
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $quoteId = $item['entity_id'];
                $quote = $this->quoteFactory->create()->load($quoteId);
                $customerName = $this->helperEmailMarketing->getCustomerName($quote);
                if ($quote->getCustomerId()) {
                    $url          = $this->urlBuilder->getUrl('customer/index/edit', ['id' => $item['customer_id']]);
                    $customerName = '<a href="' . $url . '" target="_blank">' . $customerName . '</a>';
                }

                $item[$this->getData('name')] = $customerName;
            }
        }

        return $dataSource;
    }
}
