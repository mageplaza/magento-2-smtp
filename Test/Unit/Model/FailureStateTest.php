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

namespace Mageplaza\Smtp\Test\Unit\Model;

use Magento\Framework\FlagManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Mageplaza\Smtp\Model\FailureState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FailureStateTest extends TestCase
{
    /** @var FlagManager&MockObject */
    private FlagManager&MockObject $flagManager;

    /** @var TimezoneInterface&MockObject */
    private TimezoneInterface&MockObject $timezone;

    protected function setUp(): void
    {
        $this->flagManager = $this->createMock(FlagManager::class);
        $this->timezone    = $this->createMock(TimezoneInterface::class);

        $now = new \DateTimeImmutable('2026-11-16 12:00:00');
        $this->timezone->method('date')->willReturn($now);
    }

    public function testRecordFailureThenClearKeepsOtherStoreViews(): void
    {
        $saved = [];
        $this->flagManager->method('getFlagData')->willReturnCallback(
            static function () use (&$saved) {
                return $saved;
            }
        );
        $this->flagManager->method('saveFlag')->willReturnCallback(
            static function (string $code, array $data) use (&$saved): void {
                $saved = $data;
            }
        );
        $this->flagManager->expects($this->never())->method('deleteFlag');

        $state = $this->createState();
        $state->recordFailure(1, 'Authentication failed');
        $state->recordFailure(2, 'Connection refused');
        $state->clear(1);

        $this->assertTrue($state->isFailing());
        $this->assertSame(['2'], $state->getStoreIds());
        $this->assertSame('Connection refused', $state->getMessage());
        $this->assertSame(1, $state->getFailureCount());
    }

    public function testClearRemovesTheFlagWhenNothingIsFailing(): void
    {
        $saved = [
            'stores' => [
                '1' => [
                    'message'         => 'Authentication failed',
                    'first_failed_at' => '2026-11-16 11:00:00',
                    'last_failed_at'  => '2026-11-16 11:00:00',
                    'count'           => 2,
                ],
            ],
        ];
        $this->flagManager->method('getFlagData')->willReturnCallback(
            static function () use (&$saved) {
                return $saved;
            }
        );
        $this->flagManager->expects($this->once())
            ->method('deleteFlag')
            ->with('mageplaza_smtp_send_failure')
            ->willReturnCallback(static function () use (&$saved): void {
                $saved = [];
            });

        $state = $this->createState();
        $state->clear(1);

        $this->assertFalse($state->isFailing());
    }

    public function testRepeatedFailureKeepsTheOriginalTimestamp(): void
    {
        $saved = [
            'stores' => [
                '1' => [
                    'message'         => 'first',
                    'first_failed_at' => '2026-11-16 08:00:00',
                    'last_failed_at'  => '2026-11-16 08:00:00',
                    'count'           => 1,
                ],
            ],
        ];
        $this->flagManager->method('getFlagData')->willReturn($saved);
        $this->flagManager->expects($this->once())->method('saveFlag')->with(
            'mageplaza_smtp_send_failure',
            $this->callback(static function (array $data): bool {
                $store = $data['stores']['1'];

                return $store['first_failed_at'] === '2026-11-16 08:00:00'
                    && $store['last_failed_at'] === '2026-11-16 12:00:00'
                    && $store['message'] === 'second'
                    && $store['count'] === 2;
            })
        );

        $this->createState()->recordFailure(1, 'second');
    }

    private function createState(): FailureState
    {
        return new FailureState($this->flagManager, $this->timezone);
    }
}
