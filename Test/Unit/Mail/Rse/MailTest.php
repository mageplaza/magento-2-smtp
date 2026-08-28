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

namespace Mageplaza\Smtp\Test\Unit\Mail\Rse;

use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Zend_Exception;

#[CoversClass(Mail::class)]
class MailTest extends TestCase
{
    private const STORE_ID = 1;

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $reflectionProperty = new ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }

    private function createHelperForSymfonyTransport(array $config, string $password = ''): Data&MockObject
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getSmtpConfig')->with('', self::STORE_ID)->willReturn($config);
        $helper->method('getPassword')->with(self::STORE_ID)->willReturn($password);

        return $helper;
    }

    // setSmtpOptions() — option extraction into internal per-store caches.

    public function testSetSmtpOptionsExtractsReturnPathIntoInternalCache(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['return_path' => 'a@b.com']);

        $this->assertSame(
            [self::STORE_ID => 'a@b.com'],
            $this->getProtectedProperty($mail, '_returnPath')
        );
        $this->assertNull($mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsExtractsIgnoreLogFlagIntoInternalCache(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['ignore_log' => true]);

        $this->assertFalse($this->getProtectedProperty($mail, '_emailLog')[self::STORE_ID]);
        $this->assertNull($mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsExtractsForceSentFlagIntoInternalCache(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['force_sent' => true]);

        $this->assertTrue($this->getProtectedProperty($mail, '_moduleEnable')[self::STORE_ID]);
        $this->assertNull($mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsMapsAuthenticationToAuthKeyWhenAuthNotSet(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['authentication' => 'oauth2']);

        $this->assertSame(['auth' => 'oauth2'], $mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsDoesNotOverrideExistingAuthKey(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['authentication' => 'oauth2', 'auth' => 'login']);

        $this->assertSame(['auth' => 'login'], $mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsDoesNotStoreWhenAllOptionsAreConsumed(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, [
            'return_path' => 'a@b.com',
            'ignore_log'  => true,
            'force_sent'  => true,
        ]);

        $this->assertNull($mail->getSmtpOptions(self::STORE_ID));
    }

    public function testSetSmtpOptionsStoresRemainingOptionsAfterExtraction(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $mail->setSmtpOptions(self::STORE_ID, ['host' => 'smtp.x.com', 'return_path' => 'a@b.com']);

        $this->assertSame(['host' => 'smtp.x.com'], $mail->getSmtpOptions(self::STORE_ID));
        $this->assertSame(
            [self::STORE_ID => 'a@b.com'],
            $this->getProtectedProperty($mail, '_returnPath')
        );
    }

    public function testSetSmtpOptionsReturnsThisForFluentChaining(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $this->assertSame($mail, $mail->setSmtpOptions(self::STORE_ID));
    }

    // getTransport() — Laminas\Mail\Transport\Smtp / SmtpOptions are not
    // installed in this project's vendor tree (grep confirmed no
    // `Laminas\Mail\*` namespace anywhere under vendor/), so only the
    // branches reachable WITHOUT instantiating those classes are tested here.

    public function testGetTransportThrowsWhenHostMissingFromResolvedConfig(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getSmtpConfig')->with('', self::STORE_ID)->willReturn(['port' => 25]);
        $mail = new Mail($helper);

        $this->expectException(Zend_Exception::class);
        $this->expectExceptionMessage('A host is necessary for smtp transport, but none was given');

        $mail->getTransport(self::STORE_ID);
    }

    public function testGetTransportThrowsWhenHostIsEmptyString(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getSmtpConfig')->willReturn(['host' => '', 'port' => 25]);
        $mail = new Mail($helper);

        $this->expectException(Zend_Exception::class);

        $mail->getTransport(self::STORE_ID);
    }

    public function testGetTransportReturnsCachedTransportWithoutRecomputing(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getSmtpConfig');
        $mail = new Mail($helper);
        $cachedTransport = new \stdClass();
        $this->setProtectedProperty($mail, '_transport', $cachedTransport);

        $this->assertSame($cachedTransport, $mail->getTransport(self::STORE_ID));
    }

    public function testResetTransportForcesNewTransportOnNextGetTransport(): void
    {
        if (!class_exists(\Laminas\Mail\Transport\Smtp::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $helper = $this->createMock(Data::class);
        $helper->method('getSmtpConfig')->willReturn(['host' => 'smtp.example.com', 'port' => 25]);
        $helper->method('versionCompare')->willReturn(false);
        $mail = new Mail($helper);
        $cachedTransport = new \stdClass();
        $this->setProtectedProperty($mail, '_transport', $cachedTransport);

        $mail->resetTransport();

        $this->assertNull($this->getProtectedProperty($mail, '_transport'));
        $this->assertNotSame($cachedTransport, $mail->getTransport(self::STORE_ID));
    }

    // processMessage()

    public function testProcessMessageFetchesReturnPathConfigOnFirstCall(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())
            ->method('getSmtpConfig')
            ->with('return_path_email', self::STORE_ID)
            ->willReturn('');
        $mail = new Mail($helper);

        $message = new \stdClass();

        $this->assertSame($message, $mail->processMessage($message, self::STORE_ID));
    }

    public function testProcessMessageDoesNotRefetchReturnPathConfigOnSubsequentCalls(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())->method('getSmtpConfig')->willReturn('');
        $mail = new Mail($helper);

        $message = new \stdClass();
        $mail->processMessage($message, self::STORE_ID);
        $mail->processMessage($message, self::STORE_ID);
    }

    public function testProcessMessageAddsReturnPathHeaderWhenVersionAtLeast228(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getSmtpConfig');
        $helper->method('versionCompare')->willReturn(true);
        $mail = new Mail($helper);
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => 'return@x.com']);

        $message = new class {
            public array $addHeadersCalls = [];

            public function getHeaders(): self
            {
                return $this;
            }

            public function addHeaders(array $headers): void
            {
                $this->addHeadersCalls[] = $headers;
            }
        };

        $mail->processMessage($message, self::STORE_ID);

        $this->assertSame([['Return-Path' => 'return@x.com']], $message->addHeadersCalls);
    }

    public function testProcessMessageSetsReturnPathWhenVersionBelow228AndSetReturnPathExists(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturn(false);
        $mail = new Mail($helper);
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => 'return@x.com']);

        $message = new class {
            public array $setReturnPathCalls = [];

            public function setReturnPath($path): void
            {
                $this->setReturnPathCalls[] = $path;
            }
        };

        $mail->processMessage($message, self::STORE_ID);

        $this->assertSame(['return@x.com'], $message->setReturnPathCalls);
    }

    public function testProcessMessageDoesNothingWhenVersionBelow228AndSetReturnPathMissing(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturn(false);
        $mail = new Mail($helper);
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => 'return@x.com']);

        $message = new \stdClass();

        $this->assertSame($message, $mail->processMessage($message, self::STORE_ID));
    }

    public function testProcessMessageSetsFromWhenFromByStoreSetAndHeadersArrayMissingFromKey(): void
    {
        $mail = new Mail($this->createMock(Data::class));
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => '']);
        $mail->setFromByStore('from@x.com', 'From Name');

        $message = new class {
            public array $setFromCalls = [];

            public function getHeaders(): array
            {
                return ['To' => 'to@x.com'];
            }

            public function setFrom($email, $name): void
            {
                $this->setFromCalls[] = [$email, $name];
            }
        };

        $mail->processMessage($message, self::STORE_ID);

        $this->assertSame([['from@x.com', 'From Name']], $message->setFromCalls);
    }

    public function testProcessMessageDoesNotSetFromWhenHeadersArrayHasFromKey(): void
    {
        $mail = new Mail($this->createMock(Data::class));
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => '']);
        $mail->setFromByStore('from@x.com', 'From Name');

        $message = new class {
            public array $setFromCalls = [];

            public function getHeaders(): array
            {
                return ['From' => 'existing@x.com'];
            }

            public function setFrom($email, $name): void
            {
                $this->setFromCalls[] = [$email, $name];
            }
        };

        $mail->processMessage($message, self::STORE_ID);

        $this->assertSame([], $message->setFromCalls);
    }

    public function testProcessMessageDoesNotSetFromWhenFromByStoreNotSet(): void
    {
        $mail = new Mail($this->createMock(Data::class));
        $this->setProtectedProperty($mail, '_returnPath', [self::STORE_ID => '']);

        $message = new class {
            public array $setFromCalls = [];

            public function getHeaders(): array
            {
                return [];
            }

            public function setFrom($email, $name): void
            {
                $this->setFromCalls[] = [$email, $name];
            }
        };

        $mail->processMessage($message, self::STORE_ID);

        $this->assertSame([], $message->setFromCalls);
    }

    // setFromByStore()

    public function testSetFromByStoreStoresEmailAndNameAndReturnsThis(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $result = $mail->setFromByStore('from@x.com', 'From Name');

        $this->assertSame($mail, $result);
        $this->assertSame(
            ['email' => 'from@x.com', 'name' => 'From Name'],
            $this->getProtectedProperty($mail, '_fromByStore')
        );
    }

    // isModuleEnable()

    public function testIsModuleEnableFetchesAndCachesConfigValue(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())
            ->method('isEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $mail = new Mail($helper);

        $this->assertTrue($mail->isModuleEnable(self::STORE_ID));
        $this->assertTrue($mail->isModuleEnable(self::STORE_ID));
    }

    public function testIsModuleEnableReturnsCachedValueSetViaForceSentOption(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('isEnabled');
        $mail = new Mail($helper);
        $mail->setSmtpOptions(self::STORE_ID, ['force_sent' => true]);

        $this->assertTrue($mail->isModuleEnable(self::STORE_ID));
    }

    // isDeveloperMode()

    public function testIsDeveloperModeFetchesAndCachesConfigValue(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())
            ->method('getDeveloperConfig')
            ->with('developer_mode', self::STORE_ID)
            ->willReturn('1');
        $mail = new Mail($helper);

        $this->assertSame('1', $mail->isDeveloperMode(self::STORE_ID));
        $this->assertSame('1', $mail->isDeveloperMode(self::STORE_ID));
    }

    // getSmtpOptions()

    public function testGetSmtpOptionsReturnsStoredOptions(): void
    {
        $mail = new Mail($this->createMock(Data::class));
        $mail->setSmtpOptions(self::STORE_ID, ['host' => 'smtp.x.com']);

        $this->assertSame(['host' => 'smtp.x.com'], $mail->getSmtpOptions(self::STORE_ID));
    }

    public function testGetSmtpOptionsReturnsNullWhenNotSet(): void
    {
        $mail = new Mail($this->createMock(Data::class));

        $this->assertNull($mail->getSmtpOptions(self::STORE_ID));
    }

    // isEnableEmailLog()

    public function testIsEnableEmailLogFetchesAndCachesConfigValue(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())
            ->method('getConfigGeneral')
            ->with('log_email', self::STORE_ID)
            ->willReturn(true);
        $mail = new Mail($helper);

        $this->assertTrue($mail->isEnableEmailLog(self::STORE_ID));
        $this->assertTrue($mail->isEnableEmailLog(self::STORE_ID));
    }

    public function testIsEnableEmailLogReturnsCachedValueSetViaIgnoreLogOption(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getConfigGeneral');
        $mail = new Mail($helper);
        $mail->setSmtpOptions(self::STORE_ID, ['ignore_log' => true]);

        $this->assertFalse($mail->isEnableEmailLog(self::STORE_ID));
    }

    // getSymfonyTransport() — EsmtpTransport/SocketStream are real Symfony
    // classes (installed), constructed without opening any socket; host/
    // port/tls are readable back via getStream()->getHost()/getPort()/isTLS().

    public function testGetSymfonyTransportUsesDefaultsWhenConfigEmpty(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport([]));

        $transport = $mail->getSymfonyTransport(self::STORE_ID);

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $stream = $transport->getStream();
        $this->assertSame('localhost', $stream->getHost());
        $this->assertSame(25, $stream->getPort());
        $this->assertFalse($stream->isTLS());
    }

    public function testGetSymfonyTransportForcesPort465ForSslProtocol(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['protocol' => 'ssl', 'port' => 587]));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertTrue($stream->isTLS());
        $this->assertSame(465, $stream->getPort());
    }

    public function testGetSymfonyTransportAutoDetectsTlsFromPort465(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['port' => 465]));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertTrue($stream->isTLS());
        $this->assertSame(465, $stream->getPort());
    }

    public function testGetSymfonyTransportKeepsStartTlsPlainWhenPortIsNotImplicitSsl(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['protocol' => 'tls', 'port' => 587]));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertFalse($stream->isTLS());
        $this->assertSame(587, $stream->getPort());
    }

    public function testGetSymfonyTransportPort465WinsOverTlsProtocol(): void
    {
        // Mail.php:317-329 — `$port === 465` short-circuits the implicit-SSL `if` branch before
        // the STARTTLS `elseif ($protocol === 'tls')` branch is ever reached, so a config of
        // protocol=tls + port=465 resolves to tls=true/port=465, not the STARTTLS pathway.
        $mail = new Mail($this->createHelperForSymfonyTransport(['protocol' => 'tls', 'port' => 465]));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertTrue($stream->isTLS());
        $this->assertSame(465, $stream->getPort());
    }

    public function testGetSymfonyTransportStripsSslPrefixFromHost(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['host' => 'ssl://smtp.x.com']));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertSame('smtp.x.com', $stream->getHost());
    }

    public function testGetSymfonyTransportStripsTlsPrefixFromHost(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['host' => 'tls://smtp.x.com']));

        $stream = $mail->getSymfonyTransport(self::STORE_ID)->getStream();

        $this->assertSame('smtp.x.com', $stream->getHost());
    }

    public function testGetSymfonyTransportSetsCredentialsWhenUsernameAndPasswordPresent(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['username' => 'user@x.com'], 'secret'));

        $transport = $mail->getSymfonyTransport(self::STORE_ID);

        $this->assertSame('user@x.com', $transport->getUsername());
        $this->assertSame('secret', $transport->getPassword());
    }

    public function testGetSymfonyTransportDoesNotSetCredentialsWhenPasswordMissing(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport(['username' => 'user@x.com'], ''));

        $transport = $mail->getSymfonyTransport(self::STORE_ID);

        $this->assertSame('', $transport->getUsername());
        $this->assertSame('', $transport->getPassword());
    }

    public function testGetSymfonyTransportDoesNotSetCredentialsWhenUsernameMissing(): void
    {
        $mail = new Mail($this->createHelperForSymfonyTransport([], 'secret'));

        $transport = $mail->getSymfonyTransport(self::STORE_ID);

        $this->assertSame('', $transport->getUsername());
        $this->assertSame('', $transport->getPassword());
    }
}
