<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Ui\Component\Listing\Column\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Actions::class)]
class ActionsTest extends TestCase
{
    private ContextInterface&MockObject $context;
    private UiComponentFactory&MockObject $uiComponentFactory;
    private UrlInterface&MockObject $urlBuilder;
    private Data&MockObject $helperData;
    private Actions $subject;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->uiComponentFactory = $this->createMock(UiComponentFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->helperData = $this->createMock(Data::class);

        $this->subject = new Actions(
            $this->context,
            $this->uiComponentFactory,
            $this->urlBuilder,
            $this->helperData,
            [],
            ['name' => 'actions']
        );
    }

    public function testPrepareDataSourceReturnsUnchangedWhenNoItemsKey(): void
    {
        $dataSource = ['data' => []];

        $this->urlBuilder->expects($this->never())->method('getUrl');

        $this->assertSame($dataSource, $this->subject->prepareDataSource($dataSource));
    }

    public function testPrepareDataSourceReturnsUnchangedWhenDataSourceEmpty(): void
    {
        $dataSource = [];

        $this->assertSame($dataSource, $this->subject->prepareDataSource($dataSource));
    }

    public function testPrepareDataSourceAddsViewResendDeleteActionsForSingleItem(): void
    {
        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => 'http://shop/' . $route . '/id/' . $params['id']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    ['id' => 7, 'subject' => 'Order confirmation'],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);
        $actions = $result['data']['items'][0]['actions'];

        // 'view' carries only a label — the grid JS opens the row's email_content in an
        // iframe (view/adminhtml/web/js/grid/columns/actions.js), it never navigates via href.
        $this->assertSame((string) __('View'), (string) $actions['view']['label']);
        $this->assertArrayNotHasKey('href', $actions['view']);

        $this->assertSame('http://shop/adminhtml/smtp/email/id/7', $actions['resend']['href']);
        $this->assertSame((string) __('Resend'), (string) $actions['resend']['label']);
        $this->assertSame((string) __('Resend Email'), (string) $actions['resend']['confirm']['title']);
        // SMTP-3c: the confirm dialog carries a fixed warning that resend only replays the
        // logged HTML, never the original attachment (see Mail/Transport.php's stripping of
        // multipart bodies before logging on Magento < 2.4.8).
        $this->assertSame(
            (string) __(
                'Are you sure you want to resend the email <strong>"%1"</strong>?'
                . ' Note: resending only includes the HTML content saved in this log --'
                . ' any attachment from the original email is not included. If the'
                . ' original had an attachment, resend it from the order/invoice instead.',
                'Order confirmation'
            ),
            (string) $actions['resend']['confirm']['message']
        );

        $this->assertSame('http://shop/adminhtml/smtp/delete/id/7', $actions['delete']['href']);
        $this->assertSame((string) __('Delete'), (string) $actions['delete']['label']);
        $this->assertSame((string) __('Delete Log'), (string) $actions['delete']['confirm']['title']);
        $this->assertSame(
            (string) __('Are you sure you want to delete this log?'),
            (string) $actions['delete']['confirm']['message']
        );
    }

    public function testPrepareDataSourceMutatesEveryItemByReference(): void
    {
        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => 'http://shop/' . $route . '/id/' . $params['id']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    ['id' => 1, 'subject' => 'First'],
                    ['id' => 2, 'subject' => 'Second'],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);
        $items = $result['data']['items'];

        $this->assertSame('http://shop/adminhtml/smtp/email/id/1', $items[0]['actions']['resend']['href']);
        $this->assertSame('http://shop/adminhtml/smtp/delete/id/1', $items[0]['actions']['delete']['href']);
        $this->assertSame('http://shop/adminhtml/smtp/email/id/2', $items[1]['actions']['resend']['href']);
        $this->assertSame('http://shop/adminhtml/smtp/delete/id/2', $items[1]['actions']['delete']['href']);
    }

    public function testPrepareDataSourcePreservesEmailContentUntouched(): void
    {
        // Actions.php never reads or writes 'email_content' — it passes through untouched from
        // the grid's data provider straight to the JS srcdoc iframe (M2X-63).
        $this->urlBuilder->method('getUrl')->willReturn('http://shop/x');

        $dataSource = [
            'data' => [
                'items' => [
                    ['id' => 9, 'subject' => 'Hi', 'email_content' => '<p>raw html body</p>'],
                ],
            ],
        ];

        $result = $this->subject->prepareDataSource($dataSource);

        $this->assertSame('<p>raw html body</p>', $result['data']['items'][0]['email_content']);
    }
}
