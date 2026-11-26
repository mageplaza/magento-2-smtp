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

namespace Mageplaza\Smtp\Test\Unit\Model\ResourceModel\Log;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testClearLogDeletesRowsInsteadOfTruncating(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('truncateTable');
        $connection->expects($this->once())->method('delete')->with('mageplaza_smtp_log');

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getMainTable')->willReturn('mageplaza_smtp_log');

        $collection->clearLog();
    }
}
