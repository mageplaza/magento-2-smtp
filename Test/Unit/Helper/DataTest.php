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

namespace Mageplaza\Smtp\Test\Unit\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Mageplaza\Smtp\Helper\Data;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Data::class)]
class DataTest extends TestCase
{
    // Skips the heavy AbstractHelper/AbstractData constructor chain.
    private function createHelper(array $mockedMethods = []): Data&MockObject
    {
        return $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    // Property is protected/inherited from AbstractHelper/AbstractData — no public setter.
    private function setProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty(Data::class, $property);
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($object, $value);
    }


    public function testGetSmtpConfigBuildsPath(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('configuration_option/password', null)
            ->willReturn('encrypted');

        $this->assertSame('encrypted', $helper->getSmtpConfig('password'));
    }

    public function testGetSmtpConfigWithEmptyCodeOmitsSlash(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('configuration_option', null)
            ->willReturn(['host' => 'smtp.example.com']);

        $this->assertSame(['host' => 'smtp.example.com'], $helper->getSmtpConfig());
    }


    public function testGetDeveloperConfigBuildsPath(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('developer/debug', 5)
            ->willReturn('1');

        $this->assertSame('1', $helper->getDeveloperConfig('debug', 5));
    }


    public function testGetPasswordUsesProvidedStoreId(): void
    {
        $helper = $this->createHelper(['getSmtpConfig', 'getObject']);
        $helper->expects($this->once())
            ->method('getSmtpConfig')
            ->with('password', 1)
            ->willReturn('encrypted-pw');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->with('encrypted-pw')->willReturn('plain-pw');
        $helper->method('getObject')->with(EncryptorInterface::class)->willReturn($encryptor);

        $this->assertSame('plain-pw', $helper->getPassword(1));
    }

    public function testGetPasswordFallsBackToRequestStoreParam(): void
    {
        $helper = $this->createHelper(['getSmtpConfig', 'getObject']);

        // getParam() has a defaulted 2nd arg; PHPUnit records the call as ['store', null].
        $request = $this->createMock(Http::class);
        $request->method('getParam')->with('store', null)->willReturn(2);
        $this->setProperty($helper, '_request', $request);

        $helper->expects($this->once())
            ->method('getSmtpConfig')
            ->with('password', 2)
            ->willReturn('enc');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn('dec');
        $helper->method('getObject')->willReturn($encryptor);

        $this->assertSame('dec', $helper->getPassword());
    }

    public function testGetPasswordUsesWebsiteScopeWhenNoStoreParam(): void
    {
        $helper = $this->createHelper(['getConfigValue', 'getObject']);

        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            ['store', null, null],
            ['website', null, 'base'],
        ]);
        $this->setProperty($helper, '_request', $request);

        $helper->expects($this->once())
            ->method('getConfigValue')
            ->with('smtp/configuration_option/password', 'base', ScopeInterface::SCOPE_WEBSITE)
            ->willReturn('enc-web');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn('dec-web');
        $helper->method('getObject')->willReturn($encryptor);

        $this->assertSame('dec-web', $helper->getPassword());
    }

    public function testGetPasswordFallsBackToSmtpConfigWhenNoStoreOrWebsiteParam(): void
    {
        $helper = $this->createHelper(['getSmtpConfig', 'getObject']);

        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            ['store', null, null],
            ['website', null, null],
        ]);
        $this->setProperty($helper, '_request', $request);

        $helper->expects($this->once())
            ->method('getSmtpConfig')
            ->with('password', null)
            ->willReturn('enc-default');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn('dec-default');
        $helper->method('getObject')->willReturn($encryptor);

        $this->assertSame('dec-default', $helper->getPassword());
    }

    public function testGetPasswordReturnsRawValueWhenDecryptDisabled(): void
    {
        $helper = $this->createHelper(['getSmtpConfig', 'getObject']);
        $helper->method('getSmtpConfig')->with('password', 5)->willReturn('raw-pw');
        $helper->expects($this->never())->method('getObject');

        $this->assertSame('raw-pw', $helper->getPassword(5, false));
    }


    public function testGetScopeIdUsesStoreParamWhenPresent(): void
    {
        $helper = $this->createHelper();

        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            [ScopeInterface::SCOPE_STORE, null, 3],
            [ScopeInterface::SCOPE_WEBSITE, null, null],
        ]);
        $this->setProperty($helper, '_request', $request);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');
        $this->setProperty($helper, 'storeManager', $storeManager);

        $this->assertSame(3, $helper->getScopeId());
    }

    public function testGetScopeIdFallsBackToCurrentStore(): void
    {
        $helper = $this->createHelper();

        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            [ScopeInterface::SCOPE_STORE, null, null],
            [ScopeInterface::SCOPE_WEBSITE, null, null],
        ]);
        $this->setProperty($helper, '_request', $request);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(7);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->setProperty($helper, 'storeManager', $storeManager);

        $this->assertSame(7, $helper->getScopeId());
    }

    public function testGetScopeIdUsesWebsiteDefaultStoreWhenWebsiteParamPresent(): void
    {
        $helper = $this->createHelper();

        $request = $this->createMock(Http::class);
        $request->method('getParam')->willReturnMap([
            [ScopeInterface::SCOPE_STORE, null, null],
            [ScopeInterface::SCOPE_WEBSITE, null, 'base'],
        ]);
        $this->setProperty($helper, '_request', $request);

        // getDefaultStore() is declared on the concrete Website model, not WebsiteInterface.
        $fallbackStore = $this->createMock(StoreInterface::class);
        $fallbackStore->method('getId')->willReturn(1);
        $defaultStore = $this->createMock(StoreInterface::class);
        $defaultStore->method('getId')->willReturn(9);
        $website = $this->createMock(Website::class);
        $website->method('getDefaultStore')->willReturn($defaultStore);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($fallbackStore);
        $storeManager->method('getWebsite')->with('base')->willReturn($website);
        $this->setProperty($helper, 'storeManager', $storeManager);

        $this->assertSame(9, $helper->getScopeId());
    }


    public function testGetBlacklistDelegatesToConfigGeneral(): void
    {
        $helper = $this->createHelper(['getConfigGeneral']);
        $helper->expects($this->once())
            ->method('getConfigGeneral')
            ->with('blacklist', 3)
            ->willReturn('/spam\.com/');

        $this->assertSame('/spam\.com/', $helper->getBlacklist(3));
    }


    public function testIsTestEmail(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('adminhtml_smtp_test');

        $helper = $this->createHelper();
        $this->setProperty($helper, '_request', $request);

        $this->assertTrue($helper->isTestEmail());
    }

    public function testIsTestEmailFalseForOtherActions(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_index_index');

        $helper = $this->createHelper();
        $this->setProperty($helper, '_request', $request);

        $this->assertFalse($helper->isTestEmail());
    }


    public function testGetEmailMarketingConfigWithCodeAppendsSegment(): void
    {
        $helper = $this->createHelper(['getConfigValue']);
        $helper->expects($this->once())
            ->method('getConfigValue')
            ->with('email_marketing/general/enabled', 2)
            ->willReturn('1');

        $this->assertSame('1', $helper->getEmailMarketingConfig('enabled', 2));
    }

    public function testGetEmailMarketingConfigWithEmptyCodeOmitsSegment(): void
    {
        $helper = $this->createHelper(['getConfigValue']);
        $helper->expects($this->once())
            ->method('getConfigValue')
            ->with('email_marketing/general', null)
            ->willReturn(['enabled' => '1']);

        $this->assertSame(['enabled' => '1'], $helper->getEmailMarketingConfig());
    }


    public function testIsEnableEmailMarketingDelegatesToEmailMarketingConfig(): void
    {
        $helper = $this->createHelper(['getEmailMarketingConfig']);
        $helper->expects($this->once())
            ->method('getEmailMarketingConfig')
            ->with('enabled', 4)
            ->willReturn(true);

        $this->assertTrue($helper->isEnableEmailMarketing(4));
    }


    public function testGetOauthConfigBuildsPath(): void
    {
        $helper = $this->createHelper(['getModuleConfig']);
        $helper->expects($this->once())
            ->method('getModuleConfig')
            ->with('configuration_option/oauth_client_id', 6)
            ->willReturn('client-id');

        $this->assertSame('client-id', $helper->getOauthConfig('oauth_client_id', 6));
    }


    public function testGetOauthAccessTokenReturnsNullWhenNotOauth2(): void
    {
        $helper = $this->createHelper();

        $this->assertNull($helper->getOauthAccessToken(1, ['authentication' => 'plain']));
    }

    public function testGetOauthAccessTokenThrowsOnMissingCredentials(): void
    {
        $helper = $this->createHelper();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Tenant ID');

        // secret present (no ':' so no decrypt) but tenant + client id missing.
        $helper->getOauthAccessToken(1, [
            'authentication'      => 'oauth2',
            'oauth_client_secret' => 'plain-secret',
        ]);
    }

    public function testGetOauthAccessTokenReturnsCachedToken(): void
    {
        $helper = $this->createHelper();

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('cached-token');
        $this->setProperty($helper, 'cache', $cache);

        $token = $helper->getOauthAccessToken(1, [
            'authentication'      => 'oauth2',
            'oauth_tenant_id'     => 'tenant',
            'oauth_client_id'     => 'client',
            'oauth_client_secret' => 'plain-secret',
        ]);

        $this->assertSame('cached-token', $token);
    }


    public function testShouldUseGraphApiDetectsOauth2(): void
    {
        $helper = $this->createHelper();

        $this->assertTrue($helper->shouldUseGraphApi(null, ['authentication' => 'oauth2']));
        $this->assertTrue($helper->shouldUseGraphApi(null, ['auth' => 'oauth2']));
        $this->assertFalse($helper->shouldUseGraphApi(null, ['authentication' => 'login']));
        $this->assertFalse($helper->shouldUseGraphApi(null, ['authentication' => '']));
    }

    public function testShouldUseGraphApiFallsBackToGetSmtpConfigWhenNoOverride(): void
    {
        $helper = $this->createHelper(['getSmtpConfig']);
        $helper->expects($this->once())
            ->method('getSmtpConfig')
            ->with('', 8)
            ->willReturn(['authentication' => 'oauth2']);

        $this->assertTrue($helper->shouldUseGraphApi(8));
    }
}
