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

namespace Mageplaza\Smtp\Test\Unit\Cron;

use ArrayIterator;
use Exception;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\Smtp\Cron\ClearLog;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ClearLog::class)]
class ClearLogTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private DateTime&MockObject $date;
    private CollectionFactory&MockObject $collectionFactory;
    private Data&MockObject $helper;
    private ClearLog $cron;

    protected function setUp(): void
    {
        $this->logger            = $this->createMock(LoggerInterface::class);
        $this->date               = $this->createMock(DateTime::class);
        $this->collectionFactory = $this->createPartialMock(CollectionFactory::class, ['create']);
        $this->helper             = $this->createMock(Data::class);

        $this->cron = new ClearLog(
            $this->logger,
            $this->date,
            $this->collectionFactory,
            $this->helper
        );
    }

    public function testExecuteReturnsSelfWhenModuleDisabled(): void
    {
        $this->helper->method('isEnabled')->willReturn(false);
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame($this->cron, $this->cron->execute());
    }

    public function testExecuteSkipsQueryWhenRetentionIsZero(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('getConfigGeneral')->with('clean_email')->willReturn('0');
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame($this->cron, $this->cron->execute());
    }

    public function testExecuteDeletesLogsAcrossEveryPage(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('getConfigGeneral')->with('clean_email')->willReturn('30');
        $this->date->method('date')->willReturn('2026-07-14 00:00:00');

        $pageOneLogs = [$this->createMock(Log::class), $this->createMock(Log::class)];
        $pageTwoLogs = [$this->createMock(Log::class), $this->createMock(Log::class)];
        foreach ([...$pageOneLogs, ...$pageTwoLogs] as $log) {
            $log->expects($this->once())->method('delete');
        }

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->with(1000)->willReturnSelf();
        $collection->method('getLastPageNumber')->willReturn(2);
        $collection->expects($this->exactly(2))->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(
            new ArrayIterator($pageOneLogs),
            new ArrayIterator($pageTwoLogs)
        );
        $collection->expects($this->exactly(2))->method('clear');

        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $this->assertSame($this->cron, $this->cron->execute());
    }

    public function testExecuteLogsDeleteFailureAndContinuesLoop(): void
    {
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('getConfigGeneral')->with('clean_email')->willReturn('30');
        $this->date->method('date')->willReturn('2026-07-14 00:00:00');

        $failingLog = $this->createMock(Log::class);
        $failingLog->method('delete')->willThrowException(new Exception('locked'));
        $survivingLog = $this->createMock(Log::class);
        $survivingLog->expects($this->once())->method('delete');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getLastPageNumber')->willReturn(1);
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$failingLog, $survivingLog]));
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->logger->expects($this->once())->method('critical')->with($this->isInstanceOf(Exception::class));

        $this->assertSame($this->cron, $this->cron->execute());
    }
}
