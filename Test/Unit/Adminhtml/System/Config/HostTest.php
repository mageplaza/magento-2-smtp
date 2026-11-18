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

namespace Mageplaza\Smtp\Test\Unit\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Block\Adminhtml\System\Config\Host;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(Host::class)]
class HostTest extends TestCase
{
    use MockCreationTrait;

    // Constructor is never invoked (disableOriginalConstructor). _getElementHtml() calls
    // $this->_toHtml(), which Magento\Backend\Block\Template::_toHtml() (module-backend/Block/
    // Template.php:141-145) overrides to dispatch via $this->_eventManager — null with the
    // constructor disabled. Mock _toHtml() itself so that override never runs.
    private function createSut(): Host
    {
        $sut = $this->getMockBuilder(Host::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_toHtml'])
            ->getMock();
        $sut->method('_toHtml')->willReturn('');

        return $sut;
    }

    private function createElement(): AbstractElement&MockObject
    {
        return $this->createPartialMockWithReflection(
            AbstractElement::class,
            ['getOriginalData', 'getHtmlId', 'getElementHtml']
        );
    }

    private function invokeGetElementHtml(Host $sut, AbstractElement $element): string
    {
        return (new ReflectionMethod(Host::class, '_getElementHtml'))->invoke($sut, $element);
    }


    public static function labelProvider(): array
    {
        return [
            'host key'           => ['host', __('Server Name (host)')],
            'port key'           => ['port', __('Port')],
            'protocol key'       => ['protocol', __('Protocol')],
            'tls key'            => ['tls', __('Transport Layer Security (TLS)')],
            'ssl key'            => ['ssl', __('Secure Sockets Layer (SSL)')],
            'authentication key' => ['authentication', __('Authentication')],
            'oauth2 key'         => ['oauth2', __('OAuth2')],
            'username key'       => ['username', __('Username')],
            'password key'       => ['password', __('Password')],
            'tenant_id key'      => ['tenant_id', __('Tenant ID')],
            'client_id key'      => ['client_id', __('Client ID')],
            'client_secret key'  => ['client_secret', __('Client Secret')],
            'scope key'          => ['scope', __('Scope')],
            'empty key'          => ['', __('None')],
        ];
    }

    /**
     * @dataProvider labelProvider
     */
    #[DataProvider('labelProvider')]
    public function testGetLabelReturnsExpectedPhraseForKnownKeys(string $key, Phrase $expected): void
    {
        $this->assertEquals($expected, $this->createSut()->getLabel($key));
    }

    public function testGetLabelReturnsRawKeyForUnknownKey(): void
    {
        $this->assertSame('unknown_key', $this->createSut()->getLabel('unknown_key'));
    }


    public function testGetOptionProviderReturnsFullStaticArray(): void
    {
        $result = $this->createSut()->getOptionProvider();

        // Verified against Block/Adminhtml/System/Config/Host.php:157-447 top-level key count.
        $this->assertCount(33, $result);
        $this->assertArrayHasKey('gmail', $result);
        $this->assertSame('smtp.gmail.com', $result['gmail']['info']['host']);
        $this->assertArrayHasKey('amazon', $result);
        $this->assertArrayHasKey('us-east-virginia', $result['amazon']['info']);
        $this->assertSame(
            'email-smtp.us-east-1.amazonaws.com',
            $result['amazon']['info']['us-east-virginia']['info']['host']
        );
    }

    public function testGetOptionProviderOffice365CarriesOauth2Note(): void
    {
        $result = $this->createSut()->getOptionProvider();

        $this->assertSame('oauth2', $result['office365']['info']['authentication']);
        $this->assertInstanceOf(Phrase::class, $result['office365']['note']['global']);
    }


    public function testGetElementHtmlUsesButtonLabelFromOriginalData(): void
    {
        $element = $this->createElement();
        $element->method('getOriginalData')->willReturn(['button_label' => 'Load']);
        $element->method('getHtmlId')->willReturn('mp_smtp_host');
        $element->method('getElementHtml')->willReturn('<input id="mp_smtp_host"/>');

        $sut = $this->createSut();
        $result = $this->invokeGetElementHtml($sut, $element);

        $this->assertEquals(__('Load'), $sut->getData('button_label'));
        $this->assertSame('mp_smtp_host', $sut->getData('html_id'));
        $this->assertSame('smtp.gmail.com', $sut->getData('provider')['gmail']['info']['host']);

        $decodedDataInfo = json_decode((string) $sut->getData('data_info'), true);
        $this->assertIsArray($decodedDataInfo);
        $this->assertSame('smtp.gmail.com', $decodedDataInfo['gmail']['info']['host']);

        // _toHtml() mocked to return '' in createSut() — see that method's comment.
        $this->assertSame('<input id="mp_smtp_host"/>', $result);
    }

    public function testGetElementHtmlFallsBackToDefaultButtonLabelWhenOriginalDataEmpty(): void
    {
        $element = $this->createElement();
        $element->method('getOriginalData')->willReturn([]);
        $element->method('getHtmlId')->willReturn('mp_smtp_host');
        $element->method('getElementHtml')->willReturn('<input id="mp_smtp_host"/>');

        $sut = $this->createSut();
        $this->invokeGetElementHtml($sut, $element);

        $this->assertSame('', (string) $sut->getData('button_label'));
    }
}
