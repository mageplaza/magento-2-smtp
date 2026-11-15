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

namespace Mageplaza\Smtp\Test\Unit\Plugin\Model\Resolver\Products\DataProvider\ProductSearch;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch\ProductCollectionSearchCriteriaBuilder;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaInterface;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Plugin\Model\Resolver\Products\DataProvider\ProductSearch\ProductSearchCriteriaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Registers a bodyless stub for the plugin's around-target class, which is absent from the
// installed magento/module-catalog-graph-ql package — see the stub file's own docblock.
require_once __DIR__ . '/ProductCollectionSearchCriteriaBuilderCompatStub.php';

#[CoversClass(ProductSearchCriteriaBuilder::class)]
class ProductSearchCriteriaBuilderTest extends TestCase
{
    private Data&MockObject $helperData;
    private FilterBuilder&MockObject $filterBuilder;
    private FilterGroupBuilder&MockObject $filterGroupBuilder;

    protected function setUp(): void
    {
        $this->helperData = $this->createMock(Data::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->filterGroupBuilder = $this->createMock(FilterGroupBuilder::class);
    }

    private function createSut(): ProductSearchCriteriaBuilder
    {
        return new ProductSearchCriteriaBuilder(
            $this->helperData,
            $this->filterBuilder,
            $this->filterGroupBuilder
        );
    }

    private function createSubject(): ProductCollectionSearchCriteriaBuilder&MockObject
    {
        return $this->createMock(ProductCollectionSearchCriteriaBuilder::class);
    }

    private function makeFilter(string $field, string $value, string $conditionType = 'eq'): Filter
    {
        $filter = new Filter();
        $filter->setField($field);
        $filter->setValue($value);
        $filter->setConditionType($conditionType);

        return $filter;
    }

    private function makeFilterGroup(array $filters): FilterGroup
    {
        $group = new FilterGroup();
        $group->setFilters($filters);

        return $group;
    }


    public function testAroundBuildReturnsProceedResultUnmodifiedWhenDisabled(): void
    {
        $this->helperData->method('isEnabled')->willReturn(false);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteria->expects($this->never())->method('getFilterGroups');

        $resultCriteria = $this->createMock(SearchCriteria::class);
        $resultCriteria->expects($this->never())->method('setFilterGroups');

        $this->filterBuilder->expects($this->never())->method('create');
        $this->filterGroupBuilder->expects($this->never())->method('create');

        $proceed = static fn (SearchCriteriaInterface $sc) => $resultCriteria;

        $actual = $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);

        $this->assertSame($resultCriteria, $actual);
    }

    public function testAroundBuildLeavesFilterGroupsUnmodifiedWhenNoFieldsMatch(): void
    {
        $this->helperData->method('isEnabled')->willReturn(true);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteria->method('getFilterGroups')->willReturn([
            $this->makeFilterGroup([$this->makeFilter('sku', 'ABC')]),
        ]);

        $resultCriteria = $this->createMock(SearchCriteria::class);
        $resultCriteria->expects($this->never())->method('setFilterGroups');

        $this->filterBuilder->expects($this->never())->method('create');
        $this->filterGroupBuilder->expects($this->never())->method('create');

        $proceed = static fn (SearchCriteriaInterface $sc) => $resultCriteria;

        $actual = $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);

        $this->assertSame($resultCriteria, $actual);
    }

    public function testAroundBuildRebuildsFilterGroupWhenCreatedAtFilterPresent(): void
    {
        $this->helperData->method('isEnabled')->willReturn(true);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteria->method('getFilterGroups')->willReturn([
            $this->makeFilterGroup([$this->makeFilter('created_at', '2024-01-01', 'gteq')]),
        ]);

        // Reproduces the SUT's own transform (strtotime -> date('Y-m-d H:i:s')) rather than
        // hardcoding a string, so the assertion holds regardless of the runtime timezone.
        $expectedTime = date('Y-m-d H:i:s', strtotime('2024-01-01'));

        $customFilter = new Filter();
        $this->filterBuilder->expects($this->once())->method('setField')->with('created_at')->willReturnSelf();
        $this->filterBuilder->expects($this->once())->method('setValue')->with($expectedTime)->willReturnSelf();
        $this->filterBuilder->expects($this->once())->method('setConditionType')->with('gteq')->willReturnSelf();
        $this->filterBuilder->expects($this->once())->method('create')->willReturn($customFilter);

        $createdGroup = new FilterGroup();
        $this->filterGroupBuilder->expects($this->once())->method('addFilter')
            ->with($this->identicalTo($customFilter))
            ->willReturnSelf();
        $this->filterGroupBuilder->expects($this->once())->method('create')->willReturn($createdGroup);

        $resultCriteria = $this->createMock(SearchCriteria::class);
        $resultCriteria->expects($this->once())->method('setFilterGroups')
            ->with($this->identicalTo([$createdGroup]));

        $proceed = static fn (SearchCriteriaInterface $sc) => $resultCriteria;

        $actual = $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);

        $this->assertSame($resultCriteria, $actual);
    }

    public function testAroundBuildRebuildsMultipleFilterGroupsAcrossGroups(): void
    {
        $this->helperData->method('isEnabled')->willReturn(true);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteria->method('getFilterGroups')->willReturn([
            $this->makeFilterGroup([$this->makeFilter('news_from_date', '2024-02-01')]),
            $this->makeFilterGroup([$this->makeFilter('news_to_date', '2024-03-01')]),
        ]);

        $this->filterBuilder->method('setField')->willReturnSelf();
        $this->filterBuilder->method('setValue')->willReturnSelf();
        $this->filterBuilder->method('setConditionType')->willReturnSelf();
        $this->filterBuilder->expects($this->exactly(2))->method('create')
            ->willReturn(new Filter(), new Filter());

        $createdGroup1 = new FilterGroup();
        $createdGroup2 = new FilterGroup();
        $this->filterGroupBuilder->method('addFilter')->willReturnSelf();
        $this->filterGroupBuilder->expects($this->exactly(2))->method('create')
            ->willReturn($createdGroup1, $createdGroup2);

        $resultCriteria = $this->createMock(SearchCriteria::class);
        $resultCriteria->expects($this->once())->method('setFilterGroups')
            ->with($this->identicalTo([$createdGroup1, $createdGroup2]));

        $proceed = static fn (SearchCriteriaInterface $sc) => $resultCriteria;

        $actual = $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);

        $this->assertSame($resultCriteria, $actual);
    }

    public function testAroundBuildKeepsFilterGroupsProducedByProceed(): void
    {
        $this->helperData->method('isEnabled')->willReturn(true);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteria->method('getFilterGroups')->willReturn([
            $this->makeFilterGroup([$this->makeFilter('created_at', '2024-01-01', 'gteq')]),
        ]);

        $this->filterBuilder->method('setField')->willReturnSelf();
        $this->filterBuilder->method('setValue')->willReturnSelf();
        $this->filterBuilder->method('setConditionType')->willReturnSelf();
        $this->filterBuilder->method('create')->willReturn(new Filter());

        $categoryGroup = $this->makeFilterGroup([$this->makeFilter('category_id', '5')]);
        $createdGroup = new FilterGroup();
        $this->filterGroupBuilder->method('addFilter')->willReturnSelf();
        $this->filterGroupBuilder->method('create')->willReturn($createdGroup);

        $resultCriteria = $this->createMock(SearchCriteria::class);
        $resultCriteria->method('getFilterGroups')->willReturn([$categoryGroup]);
        $resultCriteria->expects($this->once())->method('setFilterGroups')
            ->with($this->identicalTo([$categoryGroup, $createdGroup]));

        $proceed = static fn (SearchCriteriaInterface $sc) => $resultCriteria;

        $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);
    }

    public function testAroundBuildInvokesProceedExactlyOnceWithOriginalSearchCriteria(): void
    {
        $this->helperData->method('isEnabled')->willReturn(false);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $resultCriteria = $this->createMock(SearchCriteria::class);

        $callCount = 0;
        $receivedArg = null;
        $proceed = static function (SearchCriteriaInterface $sc) use (&$callCount, &$receivedArg, $resultCriteria) {
            $callCount++;
            $receivedArg = $sc;

            return $resultCriteria;
        };

        $this->createSut()->aroundBuild($this->createSubject(), $proceed, $searchCriteria);

        $this->assertSame(1, $callCount);
        $this->assertSame($searchCriteria, $receivedArg);
    }
}
