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

use Countable;
use Exception;
use Iterator;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Model\EmailSentFlagUpdater;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\Source\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

#[CoversClass(Log::class)]
class LogTest extends TestCase
{
    private Context&MockObject $context;
    private Registry&MockObject $registry;
    private TransportBuilder&MockObject $transportBuilder;
    private Mail&MockObject $mailResource;
    private Data&MockObject $helper;
    private AbstractDb&MockObject $resource;
    private LoggerInterface&MockObject $logger;
    private EmailSentFlagUpdater&MockObject $emailSentFlagUpdater;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailSentFlagUpdater = $this->createMock(EmailSentFlagUpdater::class);

        // Context has no getRegistry() — Registry is Log's own second ctor arg, not Context-supplied.
        $this->context = $this->createMock(Context::class);
        $this->context->method('getLogger')->willReturn($this->logger);

        $this->registry = $this->createMock(Registry::class);
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->mailResource = $this->createMock(Mail::class);
        $this->helper = $this->createMock(Data::class);

        // AbstractDb (not bare AbstractResource) so _init()'s _getResource()->getIdFieldName()
        // resolves to this mock instead of falling through to the real ObjectManager.
        $this->resource = $this->createMock(AbstractDb::class);
        $this->resource->method('getIdFieldName')->willReturn('id');
    }

    private function createLog(array $data = []): Log
    {
        $log = new Log(
            $this->context,
            $this->registry,
            $this->transportBuilder,
            $this->mailResource,
            $this->helper,
            $this->resource,
            null,
            [],
            null,
            null,
            $this->emailSentFlagUpdater
        );

        if ($data !== []) {
            $log->setData($data);
        }

        return $log;
    }

    private function stubTransportBuilderChain(): void
    {
        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
    }

    private function invokeExtractEmailInfo(Log $log, string $emailList): array
    {
        $method = new ReflectionMethod(Log::class, 'extractEmailInfo');
        $method->setAccessible(true);

        return $method->invoke($log, $emailList);
    }

    private function createStubAddress(string $name, string $email): object
    {
        return new class ($name, $email) {
            public function __construct(private readonly string $name, private readonly string $email)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getEmail(): string
            {
                return $this->email;
            }
        };
    }

    private function createStubAddressIterator(string $name, string $email): object
    {
        return new class ($name, $email) implements Iterator, Countable {
            private bool $consumed = false;

            public function __construct(private readonly string $name, private readonly string $email)
            {
            }

            public function current(): object
            {
                return new class ($this->name, $this->email) {
                    public function __construct(private readonly string $name, private readonly string $email)
                    {
                    }

                    public function getName(): string
                    {
                        return $this->name;
                    }

                    public function getEmail(): string
                    {
                        return $this->email;
                    }
                };
            }

            public function next(): void
            {
                $this->consumed = true;
            }

            public function key(): int
            {
                return 0;
            }

            public function valid(): bool
            {
                return !$this->consumed;
            }

            public function rewind(): void
            {
                $this->consumed = false;
            }

            public function count(): int
            {
                return 1;
            }
        };
    }

    private function createLegacyMessage(
        string $subject,
        mixed $from,
        array $to,
        array $cc,
        array $bcc,
        string $bodyText
    ): object {
        return new class ($subject, $from, $to, $cc, $bcc, $bodyText) {
            public function __construct(
                private readonly string $subject,
                private readonly mixed $from,
                private readonly array $to,
                private readonly array $cc,
                private readonly array $bcc,
                private readonly string $bodyText
            ) {
            }

            public function getSubject(): string
            {
                return $this->subject;
            }

            public function getFrom(): mixed
            {
                return $this->from;
            }

            public function getTo(): array
            {
                return $this->to;
            }

            public function getCc(): array
            {
                return $this->cc;
            }

            public function getBcc(): array
            {
                return $this->bcc;
            }

            public function getBodyText(): string
            {
                return $this->bodyText;
            }
        };
    }

    private function createRawContent(string $content): object
    {
        return new class ($content) {
            public function __construct(private readonly string $content)
            {
            }

            public function getRawContent(): string
            {
                return $this->content;
            }
        };
    }

    private function createHeaderMessage(array $headers, mixed $bodyHtml, object $body): object
    {
        return new class ($headers, $bodyHtml, $body) {
            public function __construct(
                private readonly array $headers,
                private readonly mixed $bodyHtml,
                private readonly object $body
            ) {
            }

            public function getHeaders(): array
            {
                return $this->headers;
            }

            public function getBodyHtml(): mixed
            {
                return $this->bodyHtml;
            }

            public function getBody(): object
            {
                return $this->body;
            }
        };
    }

    private function createSymfonyAddressWithName(string $name, string $address): object
    {
        return new class ($name, $address) {
            public function __construct(private readonly string $name, private readonly string $address)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getAddress(): string
            {
                return $this->address;
            }
        };
    }

    private function createSymfonyAddressEmailOnly(string $email): object
    {
        return new class ($email) {
            public function __construct(private readonly string $email)
            {
            }

            public function getEmail(): string
            {
                return $this->email;
            }
        };
    }

    private function createSymfonyMessage(
        string $subject,
        array $from,
        array $to,
        ?array $cc,
        ?array $bcc,
        ?string $htmlBody,
        ?string $textBody
    ): object {
        return new class ($subject, $from, $to, $cc, $bcc, $htmlBody, $textBody) {
            public function __construct(
                private readonly string $subject,
                private readonly array $from,
                private readonly array $to,
                private readonly ?array $cc,
                private readonly ?array $bcc,
                private readonly ?string $htmlBody,
                private readonly ?string $textBody
            ) {
            }

            public function getSubject(): string
            {
                return $this->subject;
            }

            public function getFrom(): array
            {
                return $this->from;
            }

            public function getTo(): array
            {
                return $this->to;
            }

            public function getCc(): ?array
            {
                return $this->cc;
            }

            public function getBcc(): ?array
            {
                return $this->bcc;
            }

            public function getHtmlBody(): ?string
            {
                return $this->htmlBody;
            }

            public function getTextBody(): ?string
            {
                return $this->textBody;
            }
        };
    }

    // saveLog() — legacy Zend/Laminas message path.

    public function testSaveLogSetsFieldsFromArrayFromAddress(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage(
            'Hello',
            [$this->createStubAddress('Sender', 'sender@example.com')],
            [$this->createStubAddress('', 'to@example.com')],
            [$this->createStubAddress('', 'cc@example.com')],
            [$this->createStubAddress('', 'bcc@example.com')],
            '<p>Hi</p>'
        );

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 5);

        $this->assertSame('Hello', $log->getSubject());
        $this->assertSame('Sender <sender@example.com>', $log->getSender());
        $this->assertSame('to@example.com', $log->getRecipient());
        $this->assertSame('cc@example.com', $log->getCc());
        $this->assertSame('bcc@example.com', $log->getBcc());
        $this->assertSame(htmlspecialchars('<p>Hi</p>'), $log->getEmailContent());
        $this->assertSame(Status::STATUS_SUCCESS, $log->getStatus());
        $this->assertSame(5, $log->getStoreId());
    }

    public function testSaveLogSetsSenderFromIteratorFromAddress(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage(
            'Hello',
            $this->createStubAddressIterator('Sender', 'sender@example.com'),
            [],
            [],
            [],
            'body'
        );

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertSame('Sender <sender@example.com>', $log->getSender());
    }

    public function testSaveLogSkipsSenderWhenFromEmpty(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertFalse($log->hasData('sender'));
    }

    public function testSaveLogDoesNotSetSubjectWhenAbsent(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_ERROR, 1);

        $this->assertFalse($log->hasData('subject'));
    }

    public function testSaveLogDecodesQuotedPrintableBodyWhenVersion233Available(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'A=3DB');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        // "=3D" quoted-printable decodes to "=" before escaping.
        $this->assertSame('A=B', $log->getEmailContent());
    }

    public function testSaveLogSkipsQuotedPrintableDecodeWhenVersion233Unavailable(): void
    {
        $this->helper->method('versionCompare')->willReturnCallback(
            static fn (string $ver): bool => $ver === '2.2.8'
        );

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'A=3DB');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertSame('A=3DB', $log->getEmailContent());
    }

    public function testSaveLogSetsFieldsFromHeadersArrayAndStripsAppendKey(): void
    {
        $this->helper->method('versionCompare')->willReturn(false);

        $headers = [
            'Subject' => ['Order Update'],
            'From'    => ['sender@example.com'],
            'To'      => ['a@example.com', 'append' => true],
            'Cc'      => ['cc@example.com', 'append' => true],
            'Bcc'     => ['bcc@example.com', 'append' => true],
        ];
        $message = $this->createHeaderMessage($headers, null, $this->createRawContent('fallback'));

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertSame('Order Update', $log->getSubject());
        $this->assertSame('sender@example.com', $log->getSender());
        $this->assertSame('a@example.com', $log->getRecipient());
        $this->assertSame('cc@example.com', $log->getCc());
        $this->assertSame('bcc@example.com', $log->getBcc());
        $this->assertSame(htmlspecialchars('fallback'), $log->getEmailContent());
    }

    public function testSaveLogUsesBodyHtmlObjectWhenPresent(): void
    {
        $this->helper->method('versionCompare')->willReturn(false);

        $message = $this->createHeaderMessage(
            [],
            $this->createRawContent('<b>html</b>'),
            $this->createRawContent('unused')
        );

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertSame(htmlspecialchars('<b>html</b>'), $log->getEmailContent());
        $this->assertFalse($log->hasData('subject'));
        $this->assertFalse($log->hasData('sender'));
        $this->assertFalse($log->hasData('recipient'));
    }

    public function testSaveLogDefaultsStoreIdWhenNull(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, null);

        $this->assertSame(Store::DEFAULT_STORE_ID, $log->getStoreId());
    }

    // saveLogSymfony() — Magento 2.4.8+ Symfony Mailer message path.

    public function testSaveLogSymfonySetsSubjectWhenPresent(): void
    {
        $message = $this->createSymfonyMessage('Newsletter', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('Newsletter', $log->getSubject());
    }

    public function testSaveLogSymfonyDoesNotSetSubjectWhenAbsent(): void
    {
        $message = $this->createSymfonyMessage('', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertFalse($log->hasData('subject'));
    }

    public function testSaveLogSymfonySetsSenderFromAddressMethod(): void
    {
        $message = $this->createSymfonyMessage(
            'S',
            [$this->createSymfonyAddressWithName('Jane', 'jane@example.com')],
            [],
            [],
            [],
            null,
            null
        );

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('Jane <jane@example.com>', $log->getSender());
    }

    public function testSaveLogSymfonyFallsBackToEmailWhenAddressMethodMissing(): void
    {
        $message = $this->createSymfonyMessage(
            'S',
            [$this->createSymfonyAddressEmailOnly('only@example.com')],
            [],
            [],
            [],
            null,
            null
        );

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('<only@example.com>', $log->getSender());
    }

    public function testSaveLogSymfonyJoinsToCcBccAddressesMixingAddressAndEmailMethods(): void
    {
        $message = $this->createSymfonyMessage(
            'S',
            [],
            [
                $this->createSymfonyAddressWithName('A', 'to1@example.com'),
                $this->createSymfonyAddressEmailOnly('to2@example.com'),
            ],
            [$this->createSymfonyAddressEmailOnly('cc1@example.com')],
            [$this->createSymfonyAddressWithName('B', 'bcc1@example.com')],
            null,
            null
        );

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('to1@example.com,to2@example.com', $log->getRecipient());
        $this->assertSame('cc1@example.com', $log->getCc());
        $this->assertSame('bcc1@example.com', $log->getBcc());
    }

    public function testSaveLogSymfonyDefaultsCcBccToEmptyStringWhenNull(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], null, null, null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('', $log->getCc());
        $this->assertSame('', $log->getBcc());
    }

    public function testSaveLogSymfonyUsesHtmlBodyWhenPresent(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], '<p>Hi</p>', 'ignored');

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame(htmlspecialchars('<p>Hi</p>'), $log->getEmailContent());
    }

    public function testSaveLogSymfonyFallsBackToTextBodyWhenHtmlAbsent(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, 'Plain <text>');

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame(htmlspecialchars('Plain <text>'), $log->getEmailContent());
    }

    public function testSaveLogSymfonySetsEmptyContentWhenBothBodiesAbsent(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame('', $log->getEmailContent());
    }

    public function testSaveLogSymfonyUsesProvidedStoreId(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS, 7);

        $this->assertSame(7, $log->getStoreId());
    }

    public function testSaveLogSymfonyDefaultsStoreIdToStoreDefault(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_SUCCESS);

        $this->assertSame(Store::DEFAULT_STORE_ID, $log->getStoreId());
    }

    // saveLog()/saveLogSymfony() -- SMTP-2: error_message extra data.

    public function testSaveLogSetsErrorMessageWhenProvidedInExtra(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_ERROR, 1, ['error_message' => 'Connection refused']);

        $this->assertSame('Connection refused', $log->getErrorMessage());
    }

    public function testSaveLogLeavesErrorMessageUnsetWhenExtraOmitsIt(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertFalse($log->hasData('error_message'));
    }

    public function testSaveLogSymfonySetsErrorMessageWhenProvidedInExtra(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony($message, Status::STATUS_ERROR, 1, ['error_message' => 'Timed out']);

        $this->assertSame('Timed out', $log->getErrorMessage());
    }

    // saveLog()/saveLogSymfony() -- SMTP-4: entity_type/entity_id extra data. Transport::
    // emailLog() supplies these from the registry SetTemplateVarsEntity populated; without
    // them the log row (and a later Resend) can never be linked back to the order/invoice/
    // shipment/creditmemo the email belonged to.

    public function testSaveLogSetsEntityTypeAndIdWhenProvidedInExtra(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1, ['entity_type' => 'order', 'entity_id' => 42]);

        $this->assertSame('order', $log->getEntityType());
        $this->assertSame(42, $log->getEntityId());
    }

    public function testSaveLogLeavesEntityTypeAndIdUnsetWhenExtraOmitsThem(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $message = $this->createLegacyMessage('Hello', [], [], [], [], 'body');

        $log = $this->createLog();
        $log->saveLog($message, Status::STATUS_SUCCESS, 1);

        $this->assertFalse($log->hasData('entity_type'));
        $this->assertFalse($log->hasData('entity_id'));
    }

    public function testSaveLogSymfonySetsEntityTypeAndIdWhenProvidedInExtra(): void
    {
        $message = $this->createSymfonyMessage('S', [], [], [], [], null, null);

        $log = $this->createLog();
        $log->saveLogSymfony(
            $message,
            Status::STATUS_ERROR,
            1,
            ['entity_type' => 'invoice', 'entity_id' => 7]
        );

        $this->assertSame('invoice', $log->getEntityType());
        $this->assertSame(7, $log->getEntityId());
    }

    // resendEmail().

    public function testResendEmailAddsRecipientWithoutNameWhenVersion228Available(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $toCalls = [];
        $this->transportBuilder->method('addTo')->willReturnCallback(
            static function (...$args) use (&$toCalls): void {
                $toCalls[] = $args;
            }
        );

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $result = $log->resendEmail();

        $this->assertTrue($result);
        // TransportBuilder::addTo($address, $name = '') is a concrete-class mock double —
        // PHPUnit fills the unpassed $name with its declared default, so the recorded call
        // carries both args even though Log::resendEmail() only passes $email explicitly.
        $this->assertSame([['jane@example.com', '']], $toCalls);
    }

    public function testResendEmailAddsRecipientWithNameWhenVersion228Unavailable(): void
    {
        $this->helper->method('versionCompare')->willReturn(false);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $toCalls = [];
        $this->transportBuilder->method('addTo')->willReturnCallback(
            static function (...$args) use (&$toCalls): void {
                $toCalls[] = $args;
            }
        );

        $log = $this->createLog([
            'sender'        => '"John" <john@example.com>',
            'recipient'     => '"Jane" <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
        $this->assertSame([['jane@example.com', 'Jane']], $toCalls);
    }

    public function testResendEmailBuildsTemplateAndFromFromSenderData(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $capturedIdentifier = null;
        $this->transportBuilder->method('setTemplateIdentifier')->willReturnCallback(
            function (string $identifier) use (&$capturedIdentifier) {
                $capturedIdentifier = $identifier;

                return $this->transportBuilder;
            }
        );

        $capturedOptions = null;
        $this->transportBuilder->method('setTemplateOptions')->willReturnCallback(
            function (array $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return $this->transportBuilder;
            }
        );

        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();

        $capturedFrom = null;
        $this->transportBuilder->method('setFrom')->willReturnCallback(
            function (array $from) use (&$capturedFrom) {
                $capturedFrom = $from;

                return $this->transportBuilder;
            }
        );

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
        $this->assertSame('mpsmtp_resend_email_template', $capturedIdentifier);
        $this->assertSame(['area' => Area::AREA_FRONTEND, 'store' => Store::DEFAULT_STORE_ID], $capturedOptions);
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $capturedFrom);
    }

    public function testResendEmailAddsCcAndBccWhenPresent(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $ccCalls = [];
        $this->transportBuilder->method('addCc')->willReturnCallback(
            static function (string $email) use (&$ccCalls): void {
                $ccCalls[] = $email;
            }
        );

        $bccCalls = [];
        $this->transportBuilder->method('addBcc')->willReturnCallback(
            static function (string $email) use (&$bccCalls): void {
                $bccCalls[] = $email;
            }
        );

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'cc'            => 'cc1@example.com,cc2@example.com',
            'bcc'           => 'bcc1@example.com',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
        $this->assertSame(['cc1@example.com', 'cc2@example.com'], $ccCalls);
        $this->assertSame(['bcc1@example.com'], $bccCalls);
    }

    public function testResendEmailSkipsCcAndBccWhenAbsent(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->transportBuilder->expects($this->never())->method('addCc');
        $this->transportBuilder->expects($this->never())->method('addBcc');

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
    }

    public function testResendEmailDoesNotThrowWhenEmailContentIsNull(): void
    {
        // A log row whose body was never captured (email_content NULL) must still be
        // resendable: htmlspecialchars_decode(null) raises a deprecation that developer mode
        // turns into an exception and production mode turns into a blank error page.
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => null,
        ]);

        $this->assertTrue($log->resendEmail());
    }

    public function testResendEmailReturnsFalseAndLogsWhenSendMessageThrows(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willThrowException(new Exception('smtp fail'));
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->logger->expects($this->once())->method('critical')->with('smtp fail');

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertFalse($log->resendEmail());
        $this->assertFalse($log->hasData('status'));
    }

    public function testResendEmailSetsSmtpOptionsForceSent(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->mailResource->expects($this->once())
            ->method('setSmtpOptions')
            ->with(Store::DEFAULT_STORE_ID, ['force_sent' => true]);

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
    }

    // Resend must not create a duplicate-looking row: resendEmail() sends through
    // _transportBuilder->getTransport()->sendMessage(), which the module's own Transport
    // plugin already logs as its own new row. Before this fix, resendEmail() ALSO flipped
    // the original row to SUCCESS and saved it, so the grid showed two rows with the same
    // content. Keeping the original row's status untouched (simpler than teaching the
    // plugin to skip logging during a resend) fixes the duplicate.

    public function testResendEmailDoesNotChangeOrSaveTheOriginalLogRowOnSuccess(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->resource->expects($this->never())->method('save');

        $log = $this->createLog([
            'status'        => Status::STATUS_ERROR,
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
        $this->assertSame(Status::STATUS_ERROR, $log->getStatus());
    }

    // resendEmail() -- SMTP-4: flag the linked entity's email_sent on a successful resend.

    public function testResendEmailFlagsLinkedEntityOnSuccess(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->emailSentFlagUpdater->expects($this->once())->method('updateEmailSent')
            ->with('order', 42);

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
            'entity_type'   => 'order',
            'entity_id'     => 42,
        ]);

        $this->assertTrue($log->resendEmail());
    }

    public function testResendEmailDoesNotFlagEntityWhenLinkAbsent(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->emailSentFlagUpdater->expects($this->never())->method('updateEmailSent');

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
        ]);

        $this->assertTrue($log->resendEmail());
    }

    public function testResendEmailDoesNotFlagEntityWhenSendFails(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willThrowException(new Exception('smtp fail'));
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->emailSentFlagUpdater->expects($this->never())->method('updateEmailSent');

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
            'entity_type'   => 'order',
            'entity_id'     => 42,
        ]);

        $this->assertFalse($log->resendEmail());
    }

    public function testResendEmailSwallowsExceptionFromFlagUpdaterAndStillReturnsTrue(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);
        $this->stubTransportBuilderChain();
        $this->transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $this->emailSentFlagUpdater->method('updateEmailSent')
            ->willThrowException(new Exception('db down'));
        $this->logger->expects($this->once())->method('critical')->with('db down');

        $log = $this->createLog([
            'sender'        => 'John <john@example.com>',
            'recipient'     => 'Jane <jane@example.com>',
            'email_content' => htmlspecialchars('<p>Hi</p>'),
            'entity_type'   => 'order',
            'entity_id'     => 42,
        ]);

        $this->assertTrue($log->resendEmail());
    }

    // extractEmailInfo() — protected, invoked via ReflectionMethod.

    public function testExtractEmailInfoParsesNamedAddressWhenVersion228Available(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $result = $this->invokeExtractEmailInfo($this->createLog(), 'John <j@x.com>');

        $this->assertSame(['John' => 'j@x.com'], $result);
    }

    public function testExtractEmailInfoParsesCommaListWhenVersion228Available(): void
    {
        $this->helper->method('versionCompare')->willReturn(true);

        $result = $this->invokeExtractEmailInfo($this->createLog(), 'a@x.com,b@x.com');

        $this->assertSame([0 => 'a@x.com', 1 => 'b@x.com'], $result);
    }

    public function testExtractEmailInfoParsesQuotedNameWhenVersion228Unavailable(): void
    {
        $this->helper->method('versionCompare')->willReturn(false);

        $result = $this->invokeExtractEmailInfo($this->createLog(), '"John" <j@x.com>');

        $this->assertSame(['John' => 'j@x.com'], $result);
    }

    public function testExtractEmailInfoReturnsEmptyArrayWhenNoAngleBracketFormatAndVersion228Unavailable(): void
    {
        $this->helper->method('versionCompare')->willReturn(false);

        $result = $this->invokeExtractEmailInfo($this->createLog(), 'j@x.com');

        $this->assertSame([], $result);
    }
}
