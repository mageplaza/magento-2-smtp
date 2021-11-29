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

declare(strict_types=1);

namespace Mageplaza\Smtp\Model\Resolver\Bestsellers;

use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Reports\Helper\Data as ReportsData;
use Magento\Reports\Model\Item;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\Collection;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Helper\Data;

/**
 * Class Bestsellers
 * @package Mageplaza\Smtp\Model\Resolver\Bestsellers
 */
class Bestsellers implements ResolverInterface
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var Collection
     */
    protected $bestsellersCollection;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var ReportsData
     */
    protected $reportData;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Bestsellers constructor.
     *
     * @param DateTime $dateTime
     * @param ReportsData $reportData
     * @param Data $helperData
     * @param Collection $bestsellersCollection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        DateTime $dateTime,
        ReportsData $reportData,
        Data $helperData,
        Collection $bestsellersCollection,
        StoreManagerInterface $storeManager
    ) {
        $this->dateTime              = $dateTime;
        $this->reportData            = $reportData;
        $this->helperData            = $helperData;
        $this->bestsellersCollection = $bestsellersCollection;
        $this->storeManager          = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!$this->helperData->isEnabled()) {
            throw new GraphQlInputException(__('Smtp is disabled.'));
        }

        $filters       = $args['filters'];
        $period        = isset($filters['period_type']) ? $filters['period_type'] : 'day';
        $from          = isset($filters['from']) ? $this->dateTime->date('Y-m-d', $filters['from']) : null;
        $to            = isset($filters['to']) ? $this->dateTime->date('Y-m-d', $filters['to']) : null;
        $storeId       = isset($filters['store_id']) ? $filters['store_id'] : 0;
        $showEmptyRows = isset($filters['show_empty_rows']) ? $filters['show_empty_rows'] : false;
        $periods       = [
            ReportsData::REPORT_PERIOD_TYPE_DAY,
            ReportsData::REPORT_PERIOD_TYPE_MONTH,
            ReportsData::REPORT_PERIOD_TYPE_YEAR
        ];

        if (isset($filters['period_type']) && !in_array($filters['period_type'], $periods, true)) {
            $period = 'day';
        }

        $this->validateFilters($filters);

        $collection = $this->bestsellersCollection
            ->setPeriod($period)
            ->setDateRange($from, $to)
            ->addStoreRestrictions($storeId)
            ->load();

        if ($showEmptyRows) {
            $this->reportData->prepareIntervalsCollection($collection, $from, $to, $period);
        }

        $data = [];
        /** @var Item $item */
        foreach ($collection->getItems() as $item) {
            $key = array_search($item->getPeriod(), array_column($data, 'period'));
            if ($key === 0) {
                $key = true;
            }

            if ($item->getProductId()) {
                $data[] = [
                    'period'        => $item->getPeriod(),
                    'product_id'    => $item->getProductId(),
                    'product_name'  => $item->getProductName(),
                    'product_price' => number_format((float) $item->getProductPrice(), 2),
                    'qty_ordered'   => $item->getQtyOrdered()
                ];
            } elseif (!$key) {
                $data[] = [
                    'period'        => $item->getPeriod(),
                    'product_id'    => null,
                    'product_name'  => null,
                    'product_price' => null,
                    'qty_ordered'   => null
                ];
            }
        }

        usort($data, function ($a, $b) {
            return strcasecmp($a['period'], $b['period']);
        });

        return ['mpBestsellers' => $data];
    }

    /**
     * @param array $filters
     *
     * @throws GraphQlInputException
     */
    protected function validateFilters($filters)
    {
        if (!isset($filters['from']) || !$filters['from'] || !isset($filters['to']) || !$filters['to']) {
            throw new GraphQlInputException(__('From and To fields are required.'));
        }

        if (isset($filters['store_id'])) {
            try {
                $this->storeManager->getStore($filters['store_id']);
            } catch (Exception $e) {
                throw new GraphQlInputException(__(sprintf("The store with store ID is %d doesn't exist.", $filters['store_id'])));
            }
        }
    }
}
