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

namespace Mageplaza\Smtp\Test\Unit\Observer\Quote;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Math\Random;
use Mageplaza\Smtp\Observer\Quote\SetToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(SetToken::class)]
class SetTokenTest extends TestCase
{
    private Random&MockObject $random;

    private SetToken $observer;

    protected function setUp(): void
    {
        $this->random   = $this->createMock(Random::class);
        $this->observer = new SetToken($this->random);
    }

    private function observerWithQuote(DataObject $quote): Observer&MockObject
    {
        // getQuote() is a magic accessor resolved on the real Event object.
        $event    = new Event(['quote' => $quote]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testExecuteSetsTokenWhenMissing(): void
    {
        $quote = new DataObject();
        $this->random->expects($this->once())
            ->method('getUniqueHash')
            ->willReturn('generated-hash');

        $this->observer->execute($this->observerWithQuote($quote));

        $this->assertSame('generated-hash', $quote->getData('mp_smtp_ace_token'));
    }

    public function testExecuteKeepsExistingToken(): void
    {
        $token = 'aB3xK9pQ7zLm2WvT5yHn8RcD4fGj6sBe';
        $quote = new DataObject(['mp_smtp_ace_token' => $token]);
        $this->random->expects($this->never())->method('getUniqueHash');

        $this->observer->execute($this->observerWithQuote($quote));

        $this->assertSame($token, $quote->getData('mp_smtp_ace_token'));
    }

    /**
     * The column used to be a smallint, so every stored token is a value between 0 and 32767.
     * Those values are guessable and must be replaced, not kept.
     */
    public static function legacyTokens(): array
    {
        return [
            'zero string'       => ['0'],
            'zero int'          => [0],
            'single digit'      => ['7'],
            'single digit int'  => [7],
            'two digits'        => ['70'],
            'short garbage'     => ['existing'],
        ];
    }

    #[DataProvider('legacyTokens')]
    public function testExecuteReplacesLegacyToken(mixed $legacyToken): void
    {
        $quote = new DataObject(['mp_smtp_ace_token' => $legacyToken]);
        $this->random->expects($this->once())
            ->method('getUniqueHash')
            ->willReturn('generated-hash');

        $this->observer->execute($this->observerWithQuote($quote));

        $this->assertSame('generated-hash', $quote->getData('mp_smtp_ace_token'));
    }
}
