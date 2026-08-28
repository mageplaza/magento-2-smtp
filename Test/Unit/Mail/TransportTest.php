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

namespace Mageplaza\Smtp\Test\Unit\Mail;

use Closure;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Address;
use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimePart;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Registry;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\GraphMailer;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Mageplaza\Smtp\Mail\Transport;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\LogFactory;
use Mageplaza\Smtp\Observer\Email\SetTemplateVarsEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface as SymfonyTransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message as SymfonyMessage;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\SMimePart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\RawMessage;
use TypeError;

#[CoversClass(Transport::class)]
class TransportTest extends TestCase
{
    private const STORE_ID = 1;

    private Mail&MockObject $resourceMail;
    private LogFactory&MockObject $logFactory;
    private Registry&MockObject $registry;
    private Data&MockObject $helper;
    private LoggerInterface&MockObject $logger;
    private GraphMailer&MockObject $graphMailer;

    protected function setUp(): void
    {
        $this->resourceMail = $this->createMock(Mail::class);
        $this->logFactory   = $this->createMock(LogFactory::class);
        $this->registry     = $this->createMock(Registry::class);
        $this->helper       = $this->createMock(Data::class);
        $this->logger       = $this->createMock(LoggerInterface::class);
        $this->graphMailer  = $this->createMock(GraphMailer::class);

        // Only the store-id key resolves; 'smtp_abandoned_cart' must stay null or the log path
        // treats the store id as a quote object.
        $this->registry->method('registry')->willReturnCallback(
            static fn (string $key) => $key === 'mp_smtp_store_id' ? self::STORE_ID : null
        );
        $this->helper->method('versionCompare')->willReturn(true);
        // Blacklist bypass: isTestEmail() short-circuits validateBlacklist() before getBlacklist().
        $this->helper->method('isTestEmail')->willReturn(true);
        // emailLog() no-ops unless both flags are on; the log tests override these.
        $this->helper->method('isEnabled')->willReturn(false);
        $this->resourceMail->method('isEnableEmailLog')->willReturn(false);
        $this->resourceMail->method('isModuleEnable')->willReturn(true);
        $this->resourceMail->method('isDeveloperMode')->willReturn(false);
        $this->resourceMail->method('getSmtpOptions')->willReturn([]);
    }

    private function createSut(): Transport
    {
        return new Transport(
            $this->resourceMail,
            $this->logFactory,
            $this->registry,
            $this->helper,
            $this->logger,
            $this->graphMailer
        );
    }

    private function createMessage(
        ?AbstractPart $body = null,
        array $emails = ['to@example.com'],
        ?SymfonyMessage $symfonyMessage = null
    ): EmailMessage&MockObject {
        $addresses = [];
        foreach ($emails as $email) {
            $address = $this->createMock(Address::class);
            $address->method('getEmail')->willReturn($email);
            $address->method('getName')->willReturn('');
            $addresses[] = $address;
        }

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn($addresses);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn($body ?? new TextPart('body'));
        $message->method('getSymfonyMessage')->willReturn(
            $symfonyMessage ?? new SymfonyMessage(new Headers(), $body)
        );

        return $message;
    }

    private function createSubject(EmailMessage $message): TransportInterface&MockObject
    {
        $subject = $this->createMock(TransportInterface::class);
        $subject->method('getMessage')->willReturn($message);

        return $subject;
    }

    private function createProceed(bool &$called): Closure
    {
        return static function () use (&$called): void {
            $called = true;
        };
    }

    private function sendViaSmtp(EmailMessage $message, ?Transport $sut = null): ?RawMessage
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(false);

        $captured = null;
        $transport = $this->createMock(SymfonyTransportInterface::class);
        $transport->method('send')->willReturnCallback(
            static function (RawMessage $sent) use (&$captured) {
                $captured = $sent;

                return null;
            }
        );
        $this->resourceMail->method('getSymfonyTransport')->willReturn($transport);

        $called = false;
        ($sut ?? $this->createSut())->aroundSendMessage(
            $this->createSubject($message),
            $this->createProceed($called)
        );

        $this->assertFalse($called, 'proceed() must not run while the module is enabled');

        return $captured;
    }

    // Post-graph-nosymfony: neither the Graph nor the SMTP send path converts a plain
    // EmailMessage to Symfony anymore (see testAroundSendMessageDoesNotConvertOnSmtpPath /
    // testAroundSendMessageDoesNotConvertOnGraphPathForNonEmailMessage). convertToSymfonyEmail()
    // is still reachable from emailLog() on Magento >= 2.4.8 (see
    // testEmailLogConvertsMessageOutsideDeveloperMode for that end-to-end path), but exercising
    // every MIME shape through the full aroundSendMessage() flow would drag in the SMTP branch's
    // Symfony\Component\Mailer\Transport\TransportInterface, which does not exist on Magento
    // 2.4.7 -- unrelated to what these tests are about. Call the protected method directly
    // instead via a subclass that exposes it.
    private function convertViaEmailLog(?AbstractPart $body, ?EmailMessage $message = null): Email
    {
        $sut = new class (
            $this->resourceMail,
            $this->logFactory,
            $this->registry,
            $this->helper,
            $this->logger,
            $this->graphMailer
        ) extends Transport {
            public function convertToSymfonyEmail($laminasMessage)
            {
                return parent::convertToSymfonyEmail($laminasMessage);
            }
        };

        return $sut->convertToSymfonyEmail($message ?? $this->createMessage($body));
    }

    private function attachmentNames(Email $email): array
    {
        return array_map(
            static fn (DataPart $part): ?string => $part->getFilename(),
            $email->getAttachments()
        );
    }

    // SMTP path: the message object must reach Symfony untouched.

    public function testAroundSendMessagePassesSymfonyMessageThroughUnchanged(): void
    {
        $symfonyMessage = new SymfonyMessage(new Headers(), new TextPart('hello'));
        $message = $this->createMessage(new TextPart('hello'), ['to@example.com'], $symfonyMessage);

        $this->assertSame($symfonyMessage, $this->sendViaSmtp($message));
    }

    public function testAroundSendMessageDoesNotConvertOnSmtpPath(): void
    {
        // Subclass instead of a spy mock: the SUT itself must never be mocked.
        $sut = new class (
            $this->resourceMail,
            $this->logFactory,
            $this->registry,
            $this->helper,
            $this->logger,
            $this->graphMailer
        ) extends Transport {
            public bool $converted = false;

            protected function convertToSymfonyEmail($laminasMessage)
            {
                $this->converted = true;

                return parent::convertToSymfonyEmail($laminasMessage);
            }
        };

        $this->sendViaSmtp($this->createMessage(new TextPart('hello')), $sut);

        $this->assertFalse($sut->converted);
    }

    // Graph path: a plain EmailMessage must reach GraphMailer as a raw payload array,
    // built straight from Magento's own mail objects -- no Symfony\Component\Mime\Email/
    // Address is created for this (see buildGraphPayload()), which is what lets the Graph
    // path work on Magento < 2.4.8 where egulias/email-validator is not installed.

    public function testAroundSendMessageDoesNotConvertOnGraphPathForNonEmailMessage(): void
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(true);

        // Subclass instead of a spy mock: the SUT itself must never be mocked.
        $sut = new class (
            $this->resourceMail,
            $this->logFactory,
            $this->registry,
            $this->helper,
            $this->logger,
            $this->graphMailer
        ) extends Transport {
            public bool $converted = false;

            protected function convertToSymfonyEmail($laminasMessage)
            {
                $this->converted = true;

                return parent::convertToSymfonyEmail($laminasMessage);
            }
        };

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn(new TextPart('hello'));

        $called = false;
        $sut->aroundSendMessage($this->createSubject($message), $this->createProceed($called));

        $this->assertFalse($sut->converted);
    }

    public function testAroundSendMessageSendsGraphPayloadBuiltFromEmailMessage(): void
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(true);

        $from = $this->createMock(Address::class);
        $from->method('getEmail')->willReturn('sender@example.com');
        $from->method('getName')->willReturn('Sender');

        $to = $this->createMock(Address::class);
        $to->method('getEmail')->willReturn('to@example.com');
        $to->method('getName')->willReturn('To Name');

        $message = $this->createMock(EmailMessage::class);
        $message->method('getFrom')->willReturn([$from]);
        $message->method('getTo')->willReturn([$to]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Order confirmation');
        $message->method('getBody')->willReturn(
            new MixedPart(
                new TextPart('<p>Hi</p>', 'utf-8', 'html'),
                new DataPart('PDFDATA', 'invoice.pdf', 'application/pdf')
            )
        );

        $this->graphMailer->expects($this->never())->method('sendEmail');
        $this->graphMailer->expects($this->once())->method('sendEmailPayload')->with(
            $this->callback(static function (array $payload): bool {
                return $payload['message']['subject'] === 'Order confirmation'
                    && $payload['message']['body']['contentType'] === 'HTML'
                    && $payload['message']['body']['content'] === '<p>Hi</p>'
                    && $payload['message']['toRecipients'][0]['emailAddress']['address'] === 'to@example.com'
                    && $payload['message']['toRecipients'][0]['emailAddress']['name'] === 'To Name'
                    && $payload['message']['attachments'][0]['name'] === 'invoice.pdf'
                    && $payload['message']['attachments'][0]['contentBytes'] === base64_encode('PDFDATA')
                    && $payload['saveToSentItems'] === false;
            }),
            'sender@example.com',
            self::STORE_ID,
            []
        );

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($message),
            $this->createProceed($called)
        );
    }

    public function testAroundSendMessageUsesSendEmailWhenMessageIsAlreadySymfonyEmail(): void
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(true);

        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->subject('Already Symfony')
            ->text('body');

        $this->graphMailer->expects($this->never())->method('sendEmailPayload');
        $this->graphMailer->expects($this->once())->method('sendEmail')->with($email, self::STORE_ID, []);

        $subject = $this->createMock(TransportInterface::class);
        $subject->method('getMessage')->willReturn($email);

        $called = false;
        $this->createSut()->aroundSendMessage($subject, $this->createProceed($called));
    }

    public function testAroundSendMessagePreservesNestedMultipartTree(): void
    {
        $png = new DataPart('PNGDATA', 'logo.png', 'image/png');
        $tree = new MixedPart(
            new RelatedPart(
                new AlternativePart(new TextPart('plain'), new TextPart('<p>html</p>', 'utf-8', 'html')),
                $png->asInline()
            ),
            new DataPart('PDFDATA', 'invoice.pdf', 'application/pdf')
        );
        $symfonyMessage = new SymfonyMessage(new Headers(), $tree);

        $sent = $this->sendViaSmtp($this->createMessage($tree, ['to@example.com'], $symfonyMessage));

        $this->assertSame($tree, $sent->getBody());
        $this->assertInstanceOf(RelatedPart::class, $sent->getBody()->getParts()[0]);
    }

    public function testAroundSendMessagePreservesCustomHeaders(): void
    {
        $headers = new Headers();
        $headers->addTextHeader('X-Custom', 'kept');
        $headers->addIdHeader('Message-ID', 'abc@example.com');
        $symfonyMessage = new SymfonyMessage($headers, new TextPart('hello'));

        $sent = $this->sendViaSmtp(
            $this->createMessage(new TextPart('hello'), ['to@example.com'], $symfonyMessage)
        );

        $this->assertSame('kept', $sent->getHeaders()->getHeaderBody('X-Custom'));
        $this->assertTrue($sent->getHeaders()->has('Message-ID'));
    }

    public function testAroundSendMessageConvertsWhenSymfonyMessageUnavailable(): void
    {
        $legacyMessage = new class {
            public function getTo(): array
            {
                return [];
            }

            public function getFrom(): array
            {
                return [];
            }

            public function getCc(): array
            {
                return [];
            }

            public function getBcc(): array
            {
                return [];
            }

            public function getReplyTo(): array
            {
                return [];
            }

            public function getSubject(): string
            {
                return 'Legacy';
            }

            public function getBody(): AbstractPart
            {
                return new TextPart('legacy body');
            }
        };

        $this->helper->method('shouldUseGraphApi')->willReturn(false);

        $captured = null;
        $transport = $this->createMock(SymfonyTransportInterface::class);
        $transport->method('send')->willReturnCallback(
            static function (RawMessage $sent) use (&$captured) {
                $captured = $sent;

                return null;
            }
        );
        $this->resourceMail->method('getSymfonyTransport')->willReturn($transport);

        $subject = $this->createMock(TransportInterface::class);
        $subject->method('getMessage')->willReturn($legacyMessage);

        $called = false;
        $this->createSut()->aroundSendMessage($subject, $this->createProceed($called));

        $this->assertInstanceOf(Email::class, $captured);
        $this->assertSame('legacy body', $captured->getTextBody());
    }

    // SMTP-2: a send failure must be logged with a reason, not just swallowed
    // into a bare emailLog($message, false) + rethrow.

    public function testAroundSendMessageLogsErrorReasonWhenSendFails(): void
    {
        if (!class_exists(\Laminas\Mail\Message::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $this->helper = $this->legacyHelper();

        $laminasMessage = new \Laminas\Mail\Message();
        $laminasMessage->setSubject('Test Subject');
        $laminasMessage->addTo('victim@example.com');
        $this->resourceMail->method('processMessage')->willReturn($laminasMessage);

        $transport = $this->createMock(\Laminas\Mail\Transport\Smtp::class);
        $transport->method('send')->willThrowException(new \RuntimeException('Connection refused by host'));
        $this->resourceMail->method('getTransport')->willReturn($transport);

        $this->logger->expects($this->once())->method('error')->with(
            $this->callback(static function (string $logged): bool {
                return str_contains($logged, 'Connection refused by host')
                    && str_contains($logged, (string) self::STORE_ID)
                    && str_contains($logged, 'victim@example.com')
                    && str_contains($logged, 'Test Subject');
            })
        );

        $called = false;

        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createMock(EmailMessage::class)),
                $this->createProceed($called)
            );
            $this->fail('Expected MailException to propagate.');
        } catch (MailException $e) {
            // Expected: the retry logic does not retry a plain \RuntimeException,
            // so the outer catch in aroundSendMessage() wraps and rethrows it.
        }
    }

    public function testAroundSendMessageLoggedReasonIsTruncatedTo1000Characters(): void
    {
        if (!class_exists(\Laminas\Mail\Message::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $this->helper = $this->legacyHelper();

        $laminasMessage = new \Laminas\Mail\Message();
        $this->resourceMail->method('processMessage')->willReturn($laminasMessage);

        $transport = $this->createMock(\Laminas\Mail\Transport\Smtp::class);
        $transport->method('send')->willThrowException(new \RuntimeException(str_repeat('x', 2000)));
        $this->resourceMail->method('getTransport')->willReturn($transport);

        $this->logger->expects($this->once())->method('error')->with(
            $this->callback(static function (string $logged): bool {
                return substr_count($logged, 'x') <= 1000;
            })
        );

        $called = false;
        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createMock(EmailMessage::class)),
                $this->createProceed($called)
            );
        } catch (MailException $e) {
            // Expected.
        }
    }

    // SMTP-1: legacy (< 2.4.8) transport retry on a transient send failure.
    // A cached Laminas\Mail\Transport\Smtp reuses a socket that the remote
    // server may have dropped; a single retry after resetTransport() should
    // recover, but a second consecutive failure must still propagate.

    private function legacyHelper(): Data&MockObject
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturnCallback(
            static fn (string $version): bool => !in_array($version, ['2.4.8', '2.2.8', '2.3.3'], true)
        );
        $helper->method('shouldUseGraphApi')->willReturn(false);
        $helper->method('isTestEmail')->willReturn(true);

        return $helper;
    }

    public function testAroundSendMessageRetriesOnceWhenLegacyTransportSendThrowsRuntimeExceptionThenSucceeds(): void
    {
        if (!class_exists(\Laminas\Mail\Message::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $this->helper = $this->legacyHelper();

        $laminasMessage = new \Laminas\Mail\Message();
        $this->resourceMail->method('processMessage')->willReturn($laminasMessage);

        $sendCallCount = 0;
        $transport = $this->createMock(\Laminas\Mail\Transport\Smtp::class);
        $transport->method('send')->willReturnCallback(
            function () use (&$sendCallCount) {
                $sendCallCount++;
                if ($sendCallCount === 1) {
                    throw new \Laminas\Mail\Protocol\Exception\RuntimeException('Could not read from remote host');
                }

                return null;
            }
        );
        $this->resourceMail->method('getTransport')->willReturn($transport);
        $this->resourceMail->expects($this->once())->method('resetTransport');

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createMock(EmailMessage::class)),
            $this->createProceed($called)
        );

        $this->assertSame(2, $sendCallCount);
    }

    public function testAroundSendMessageRethrowsWhenLegacyTransportSendFailsTwice(): void
    {
        if (!class_exists(\Laminas\Mail\Message::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $this->helper = $this->legacyHelper();

        $laminasMessage = new \Laminas\Mail\Message();
        $this->resourceMail->method('processMessage')->willReturn($laminasMessage);

        $sendCallCount = 0;
        $transport = $this->createMock(\Laminas\Mail\Transport\Smtp::class);
        $transport->method('send')->willReturnCallback(
            function () use (&$sendCallCount) {
                $sendCallCount++;
                throw new \Laminas\Mail\Protocol\Exception\RuntimeException('Could not read from remote host');
            }
        );
        $this->resourceMail->method('getTransport')->willReturn($transport);
        $this->resourceMail->expects($this->once())->method('resetTransport');

        $called = false;

        $this->expectException(MailException::class);

        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createMock(EmailMessage::class)),
                $this->createProceed($called)
            );
        } finally {
            $this->assertSame(2, $sendCallCount);
        }
    }

    // Conversion (Graph + log paths) must survive every MIME shape.

    public function testConvertPlainTextRootIsUnchanged(): void
    {
        $email = $this->convertViaEmailLog(new TextPart('plain body'));

        $this->assertSame('plain body', $email->getTextBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertHtmlRootIsUnchanged(): void
    {
        $email = $this->convertViaEmailLog(new TextPart('<p>html body</p>', 'utf-8', 'html'));

        $this->assertSame('<p>html body</p>', $email->getHtmlBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertAlternativePartKeepsBothBodies(): void
    {
        $email = $this->convertViaEmailLog(
            new AlternativePart(new TextPart('plain'), new TextPart('<p>html</p>', 'utf-8', 'html'))
        );

        $this->assertSame('plain', $email->getTextBody());
        $this->assertSame('<p>html</p>', $email->getHtmlBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertMixedAlternativeWithAttachmentKeepsBodyAndAttachment(): void
    {
        $email = $this->convertViaEmailLog(
            new MixedPart(
                new AlternativePart(new TextPart('plain'), new TextPart('<p>html</p>', 'utf-8', 'html')),
                new DataPart('id,name', 'export.csv', 'text/csv')
            )
        );

        $this->assertSame('plain', $email->getTextBody());
        $this->assertSame('<p>html</p>', $email->getHtmlBody());
        $this->assertSame(['export.csv'], $this->attachmentNames($email));
    }

    public function testConvertMixedWithTwoAttachmentsKeepsOrder(): void
    {
        $email = $this->convertViaEmailLog(
            new MixedPart(
                new TextPart('plain'),
                new DataPart('A', 'first.pdf', 'application/pdf'),
                new DataPart('B', 'second.pdf', 'application/pdf')
            )
        );

        $this->assertSame('plain', $email->getTextBody());
        $this->assertSame(['first.pdf', 'second.pdf'], $this->attachmentNames($email));
    }

    public function testConvertNestedRelatedTreeKeepsBodiesAndAttachments(): void
    {
        $png = new DataPart('PNGDATA', 'logo.png', 'image/png');
        $email = $this->convertViaEmailLog(
            new MixedPart(
                new RelatedPart(
                    new AlternativePart(new TextPart('plain'), new TextPart('<p>html</p>', 'utf-8', 'html')),
                    $png->asInline()
                ),
                new DataPart('PDFDATA', 'invoice.pdf', 'application/pdf')
            )
        );

        $this->assertSame('plain', $email->getTextBody());
        $this->assertSame('<p>html</p>', $email->getHtmlBody());
        $this->assertSame(['logo.png', 'invoice.pdf'], $this->attachmentNames($email));
    }

    public function testConvertBinaryAttachmentPayloadIsByteIdentical(): void
    {
        $payload = random_bytes(64);
        $email = $this->convertViaEmailLog(
            new MixedPart(new TextPart('plain'), new DataPart($payload, 'blob.pdf', 'application/pdf'))
        );

        $this->assertSame(hash('sha256', $payload), hash('sha256', $email->getAttachments()[0]->getBody()));
    }

    public function testConvertUtf8BodyIsByteIdentical(): void
    {
        $body = 'Xin chào — tiếng Việt có dấu';
        $email = $this->convertViaEmailLog(
            new MixedPart(new TextPart($body), new DataPart('X', 'a.txt', 'text/plain'))
        );

        $this->assertSame($body, $email->getTextBody());
    }

    public function testConvertBareDataPartRootBecomesAttachmentNotBody(): void
    {
        $email = $this->convertViaEmailLog(new DataPart('FILEDATA', 'only.pdf', 'application/pdf'));

        $this->assertSame(['only.pdf'], $this->attachmentNames($email));
        $this->assertNull($email->getTextBody());
        $this->assertNull($email->getHtmlBody());
    }

    public function testConvertHtmlDataPartRootIsNotUsedAsBody(): void
    {
        $email = $this->convertViaEmailLog(new DataPart('<h1>file</h1>', 'page.html', 'text/html'));

        $this->assertSame(['page.html'], $this->attachmentNames($email));
        $this->assertNull($email->getHtmlBody());
    }

    public function testConvertTextPlainAttachmentDoesNotOverwriteHtmlBody(): void
    {
        // DataPart extends TextPart, so an attachment-first tree used to hijack the body.
        $email = $this->convertViaEmailLog(
            new MixedPart(
                new DataPart('attached text', 'note.txt', 'text/plain'),
                new TextPart('<p>real body</p>', 'utf-8', 'html')
            )
        );

        $this->assertSame('<p>real body</p>', $email->getHtmlBody());
        $this->assertSame(['note.txt'], $this->attachmentNames($email));
        $this->assertNull($email->getTextBody());
    }

    public function testConvertSMimeRootIsPreservedVerbatim(): void
    {
        $smime = new SMimePart('ENCRYPTED', 'application', 'pkcs7-mime', ['smime-type' => 'enveloped-data']);

        $email = $this->convertViaEmailLog($smime);

        $this->assertSame($smime, $email->getBody());
    }

    public function testConvertPreservesNonUtf8Charset(): void
    {
        $email = $this->convertViaEmailLog(new TextPart('body', 'iso-8859-1', 'html'));

        $this->assertSame('iso-8859-1', $email->getHtmlCharset());
    }

    public function testConvertAttachmentsOnlyDoesNotInjectPlaceholder(): void
    {
        $email = $this->convertViaEmailLog(
            new MixedPart(
                new DataPart('A', 'a.pdf', 'application/pdf'),
                new DataPart('B', 'b.pdf', 'application/pdf')
            )
        );

        $this->assertSame(['a.pdf', 'b.pdf'], $this->attachmentNames($email));
        $this->assertNull($email->getTextBody());
        $this->assertNull($email->getHtmlBody());
    }

    public function testConvertCalendarPartBecomesAttachmentNotBody(): void
    {
        $email = $this->convertViaEmailLog(
            new MixedPart(new TextPart('plain body'), new TextPart('BEGIN:VCALENDAR', 'utf-8', 'calendar'))
        );

        $this->assertSame('plain body', $email->getTextBody());
        $this->assertCount(1, $email->getAttachments());
    }

    public function testConvertFallsBackToUtf8WhenContentTypeIsNotParameterized(): void
    {
        $part = new TextPart('body');
        $part->getHeaders()->addTextHeader('Content-Type', 'text/plain');

        $email = $this->convertViaEmailLog($part);

        $this->assertSame('utf-8', $email->getTextCharset());
    }

    // SMTP-5: on Magento < 2.4.8, EmailMessage::getBody() returns a real
    // Laminas\Mime\Message (see Magento\Framework\Mail\Message::getBody()),
    // not a Symfony\Component\Mime\Part\AbstractPart. convertToSymfonyEmail()
    // must not silently drop that body as "No readable content.".
    // A mock EmailMessage is used deliberately (not a Laminas one) because
    // this is the exact object aroundSendMessage() hands to convertToSymfonyEmail()
    // in production; only getBody()'s return value needs to be the real
    // Laminas\Mime\Message type this bug is about.

    public function testConvertHandlesLaminasMimeMessageBodyWithHtmlAndAttachment(): void
    {
        if (!class_exists(\Laminas\Mime\Part::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $htmlPart = new \Laminas\Mime\Part('<p>Hello</p>');
        $htmlPart->type = 'text/html';
        $htmlPart->charset = 'utf-8';

        $attachmentPart = new \Laminas\Mime\Part('PDFDATA');
        $attachmentPart->type = 'application/pdf';
        $attachmentPart->disposition = \Laminas\Mime\Mime::DISPOSITION_ATTACHMENT;
        $attachmentPart->encoding = \Laminas\Mime\Mime::ENCODING_BASE64;
        $attachmentPart->filename = 'invoice.pdf';

        $mimeMessage = new \Laminas\Mime\Message();
        $mimeMessage->setParts([$htmlPart, $attachmentPart]);

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn($mimeMessage);

        $email = $this->convertViaEmailLog(null, $message);

        $this->assertNotSame('No readable content.', $email->getHtmlBody());
        $this->assertSame('<p>Hello</p>', $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame(['invoice.pdf'], $this->attachmentNames($email));
    }

    // Production shape: TransportBuilder/EmailMessage hand
    // Laminas\Mime\Message::setParts() an array of Magento\Framework\Mail\MimePart
    // objects (Magento's own MimePartInterface wrapper), NOT raw
    // Laminas\Mime\Part -- they only expose the same accessor method names.
    // A mock-only test using Laminas\Mime\Part alone would miss this.
    public function testConvertHandlesMagentoMimePartObjectsInsideLaminasMimeMessage(): void
    {
        if (!class_exists(\Laminas\Mime\Message::class)) {
            $this->markTestSkipped('Laminas mail/mime is not installed (Magento >= 2.4.8).');
        }

        $htmlPart = new MimePart(
            '<p>Real Magento part</p>',
            MimeInterface::TYPE_HTML,
            null,
            MimeInterface::DISPOSITION_INLINE,
            MimeInterface::ENCODING_QUOTED_PRINTABLE,
            null,
            [],
            'utf-8'
        );
        $attachmentPart = new MimePart(
            'PDFDATA',
            'application/pdf',
            'invoice.pdf',
            MimeInterface::DISPOSITION_ATTACHMENT,
            MimeInterface::ENCODING_BASE64
        );

        $mimeMessage = new \Laminas\Mime\Message();
        $mimeMessage->setParts([$htmlPart, $attachmentPart]);

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn($mimeMessage);

        $email = $this->convertViaEmailLog(null, $message);

        $this->assertSame('<p>Real Magento part</p>', $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame(['invoice.pdf'], $this->attachmentNames($email));
    }

    public function testConvertEmptyBodyFallsBackToPlaceholder(): void
    {
        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willThrowException(new TypeError('no body'));
        $message->method('getSymfonyMessage')->willReturn(new SymfonyMessage(new Headers()));

        $email = $this->convertViaEmailLog(null, $message);

        $this->assertSame('No readable content.', $email->getTextBody());
    }

    // Branch selection, error handling and the header/address riders.

    public function testAroundSendMessageCallsProceedWhenModuleDisabled(): void
    {
        $resourceMail = $this->createMock(Mail::class);
        $resourceMail->method('isModuleEnable')->willReturn(false);
        $this->resourceMail = $resourceMail;

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createMock(TransportInterface::class),
            $this->createProceed($called)
        );

        $this->assertTrue($called);
    }

    public function testAroundSendMessageSkipsSendingForBlacklistedRecipient(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturn(true);
        $helper->method('isTestEmail')->willReturn(false);
        $helper->method('getBlacklist')->willReturn('/@spam\.com/');
        $this->helper = $helper;

        $this->resourceMail->expects($this->never())->method('getSymfonyTransport');

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createMessage(new TextPart('x'), ['user@spam.com'])),
            $this->createProceed($called)
        );

        $this->assertFalse($called);
    }

    public function testAroundSendMessageWrapsTypeErrorInMailException(): void
    {
        // TypeError extends Error, not Exception — a narrow catch would let it escape as a fatal.
        $this->helper->method('shouldUseGraphApi')->willReturnCallback(
            static function (): bool {
                throw new TypeError('boom');
            }
        );

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('boom');

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createMessage(new TextPart('x'))),
            $this->createProceed($called)
        );
    }

    public function testAroundSendMessageWrapsTransportFailureInMailException(): void
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(false);

        $transport = $this->createMock(SymfonyTransportInterface::class);
        $transport->method('send')->willThrowException(new \RuntimeException('smtp down'));
        $this->resourceMail->method('getSymfonyTransport')->willReturn($transport);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('smtp down');

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createMessage(new TextPart('x'))),
            $this->createProceed($called)
        );
    }

    public function testConvertKeepsEveryReplyToAddress(): void
    {
        $replyTo = [];
        foreach (['a@example.com', 'b@example.com'] as $email) {
            $address = $this->createMock(Address::class);
            $address->method('getEmail')->willReturn($email);
            $address->method('getName')->willReturn('');
            $replyTo[] = $address;
        }

        // Built inline rather than via createMessage(): a second method() call cannot override an
        // already-configured stub, so the helper's empty getReplyTo() would win.
        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn($replyTo);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn(new TextPart('x'));
        $message->method('getSymfonyMessage')->willReturn(new SymfonyMessage(new Headers(), new TextPart('x')));

        $this->assertCount(2, $this->convertViaEmailLog(null, $message)->getReplyTo());
    }

    public function testConvertDoesNotDuplicateCcFromClonedHeaders(): void
    {
        $ccAddress = $this->createMock(Address::class);
        $ccAddress->method('getEmail')->willReturn('cc@example.com');
        $ccAddress->method('getName')->willReturn('');

        $headers = new Headers();
        $headers->addMailboxListHeader('Cc', ['cc@example.com']);

        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([$ccAddress]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn(new TextPart('x'));
        $message->method('getSymfonyMessage')->willReturn(new SymfonyMessage($headers, new TextPart('x')));

        $this->assertCount(1, $this->convertViaEmailLog(null, $message)->getCc());
    }

    public function testEmailLogConvertsMessageOutsideDeveloperMode(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturn(true);
        $helper->method('isTestEmail')->willReturn(true);
        $helper->method('isEnabled')->willReturn(true);
        $helper->method('shouldUseGraphApi')->willReturn(false);
        $this->helper = $helper;

        $resourceMail = $this->createMock(Mail::class);
        $resourceMail->method('isModuleEnable')->willReturn(true);
        $resourceMail->method('isDeveloperMode')->willReturn(false);
        $resourceMail->method('getSmtpOptions')->willReturn([]);
        $resourceMail->method('isEnableEmailLog')->willReturn(true);
        $resourceMail->method('getSymfonyTransport')
            ->willReturn($this->createMock(SymfonyTransportInterface::class));
        $this->resourceMail = $resourceMail;

        $logged = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            static function ($message) use (&$logged) {
                $logged = $message;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createMessage(new TextPart('logged body'))),
            $this->createProceed($called)
        );

        $this->assertInstanceOf(Email::class, $logged);
        $this->assertSame('logged body', $logged->getTextBody());
    }

    // SMTP-2 (DB): emailLog() must persist the failure reason alongside the existing
    // saveLogSymfony() call. The Graph API path is used deliberately here (not SMTP/Symfony
    // Mailer) since it never touches getSymfonyMessage()/Symfony\Component\Mailer\Transport\
    // TransportInterface -- both unavailable in this Magento < 2.4.8 test environment and
    // already responsible for the accepted 43-error baseline; a manually-built EmailMessage
    // mock (no getSymfonyMessage stub) keeps these new tests out of that bucket.

    // $withSymfonyMessage stubs getSymfonyMessage() so convertToSymfonyEmail() -- called from
    // emailLog()'s saveLogSymfony branch -- gets a real Symfony\Component\Mime\Message instead
    // of relying on PHPUnit's auto-generated return value for the unstubbed method. On Magento
    // >= 2.4.8, EmailMessage::getSymfonyMessage() really exists, so method_exists() on the mock
    // is true there too; without this stub, PHPUnit auto-generates a return value and then fails
    // trying to mock the final Symfony\Component\Mime\Header\Headers class when getHeaders() is
    // called on it -- an exception that emailLog()'s catch (Exception $e) swallows silently,
    // so saveLogSymfony() is never reached.
    private function createBasicMessage(?AbstractPart $body = null, bool $withSymfonyMessage = false): EmailMessage&MockObject
    {
        $message = $this->createMock(EmailMessage::class);
        $message->method('getTo')->willReturn([]);
        $message->method('getFrom')->willReturn([]);
        $message->method('getCc')->willReturn([]);
        $message->method('getBcc')->willReturn([]);
        $message->method('getReplyTo')->willReturn([]);
        $message->method('getSubject')->willReturn('Subject');
        $message->method('getBody')->willReturn($body ?? new TextPart('body'));
        if ($withSymfonyMessage) {
            // EmailMessage::getSymfonyMessage() only exists on Magento >= 2.4.8 -- stubbing a
            // method the mocked class doesn't declare throws MethodCannotBeConfiguredException,
            // so skip rather than let that surface as an error on older Magento.
            if (!method_exists(EmailMessage::class, 'getSymfonyMessage')) {
                $this->markTestSkipped('EmailMessage::getSymfonyMessage() is not available (Magento >= 2.4.8).');
            }
            $message->method('getSymfonyMessage')->willReturn(
                new SymfonyMessage(new Headers(), $body ?? new TextPart('body'))
            );
        }

        return $message;
    }

    // $useSymfonyBranch controls the mocked versionCompare('2.4.8') result, which is what
    // emailLog() branches on to call saveLog() (legacy) vs saveLogSymfony() -- independent of
    // which Magento version phpunit actually runs on, so both branches are covered on either
    // version. Every other version check (e.g. getMessage()'s versionCompare('2.2.0'), which
    // picks $transport->getMessage() vs a reflection fallback for Magento < 2.2.0) must still
    // resolve true -- this environment is always >= 2.2.0 -- so only the '2.4.8' argument is
    // pinned to $useSymfonyBranch.
    private function enableLoggingViaGraphHelper(bool $useSymfonyBranch = false): Data&MockObject
    {
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturnCallback(
            static fn (string $version): bool => $version === '2.4.8' ? $useSymfonyBranch : true
        );
        $helper->method('isTestEmail')->willReturn(true);
        $helper->method('isEnabled')->willReturn(true);
        $helper->method('shouldUseGraphApi')->willReturn(true);

        return $helper;
    }

    private function enableLoggingResourceMail(): Mail&MockObject
    {
        $resourceMail = $this->createMock(Mail::class);
        $resourceMail->method('isModuleEnable')->willReturn(true);
        $resourceMail->method('isDeveloperMode')->willReturn(false);
        $resourceMail->method('getSmtpOptions')->willReturn([]);
        $resourceMail->method('isEnableEmailLog')->willReturn(true);

        return $resourceMail;
    }

    public function testEmailLogPassesErrorMessageWhenSendFails(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper();
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willThrowException(new \RuntimeException('smtp exploded'));

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLog')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createBasicMessage()),
                $this->createProceed($called)
            );
            $this->fail('Expected MailException to propagate.');
        } catch (MailException $e) {
            // Expected.
        }

        $this->assertSame('smtp exploded', $capturedExtra['error_message']);
    }

    public function testEmailLogPassesErrorMessageWhenSendFailsSymfonyBranch(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper(true);
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willThrowException(new \RuntimeException('smtp exploded'));

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createBasicMessage(withSymfonyMessage: true)),
                $this->createProceed($called)
            );
            $this->fail('Expected MailException to propagate.');
        } catch (MailException $e) {
            // Expected.
        }

        $this->assertSame('smtp exploded', $capturedExtra['error_message']);
    }

    public function testEmailLogTruncatesErrorMessageTo1000Characters(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper();
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willThrowException(new \RuntimeException(str_repeat('y', 2000)));

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLog')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createBasicMessage()),
                $this->createProceed($called)
            );
        } catch (MailException $e) {
            // Expected.
        }

        $this->assertSame(1000, strlen($capturedExtra['error_message']));
    }

    public function testEmailLogTruncatesErrorMessageTo1000CharactersSymfonyBranch(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper(true);
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willThrowException(new \RuntimeException(str_repeat('y', 2000)));

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        try {
            $this->createSut()->aroundSendMessage(
                $this->createSubject($this->createBasicMessage(withSymfonyMessage: true)),
                $this->createProceed($called)
            );
        } catch (MailException $e) {
            // Expected.
        }

        $this->assertSame(1000, strlen($capturedExtra['error_message']));
    }

    public function testEmailLogDoesNotIncludeErrorMessageWhenSendSucceeds(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper();
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLog')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage()),
            $this->createProceed($called)
        );

        $this->assertArrayNotHasKey('error_message', $capturedExtra);
    }

    public function testEmailLogDoesNotIncludeErrorMessageWhenSendSucceedsSymfonyBranch(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper(true);
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage(withSymfonyMessage: true)),
            $this->createProceed($called)
        );

        $this->assertArrayNotHasKey('error_message', $capturedExtra);
    }

    // SMTP-4: emailLog() must pick up the entity_type/entity_id that
    // Observer\Email\SetTemplateVarsEntity stashed in the registry while the email template
    // was being built, persist them alongside the log row, and always clear the registry key
    // afterwards -- even when logging itself is disabled -- so it can never leak onto the
    // next, unrelated email sent in the same request.

    public function testEmailLogPassesEntityFromRegistryAndClearsIt(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper();
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn (string $key) => $key === SetTemplateVarsEntity::REGISTRY_KEY
                ? ['entity_type' => 'order', 'entity_id' => 42]
                : null
        );
        $registry->expects($this->once())->method('unregister')->with(SetTemplateVarsEntity::REGISTRY_KEY);
        $this->registry = $registry;

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLog')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage()),
            $this->createProceed($called)
        );

        $this->assertSame('order', $capturedExtra['entity_type']);
        $this->assertSame(42, $capturedExtra['entity_id']);
    }

    public function testEmailLogPassesEntityFromRegistryAndClearsItSymfonyBranch(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper(true);
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn (string $key) => $key === SetTemplateVarsEntity::REGISTRY_KEY
                ? ['entity_type' => 'order', 'entity_id' => 42]
                : null
        );
        $registry->expects($this->once())->method('unregister')->with(SetTemplateVarsEntity::REGISTRY_KEY);
        $this->registry = $registry;

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage(withSymfonyMessage: true)),
            $this->createProceed($called)
        );

        $this->assertSame('order', $capturedExtra['entity_type']);
        $this->assertSame(42, $capturedExtra['entity_id']);
    }

    public function testEmailLogOmitsEntityDataWhenRegistryEmpty(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper();
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLog')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage()),
            $this->createProceed($called)
        );

        $this->assertArrayNotHasKey('entity_type', $capturedExtra);
        $this->assertArrayNotHasKey('entity_id', $capturedExtra);
    }

    public function testEmailLogOmitsEntityDataWhenRegistryEmptySymfonyBranch(): void
    {
        $this->helper = $this->enableLoggingViaGraphHelper(true);
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $capturedExtra = null;
        $log = $this->createMock(Log::class);
        $log->method('saveLogSymfony')->willReturnCallback(
            function ($message, $status, $storeId, array $extra = []) use (&$capturedExtra) {
                $capturedExtra = $extra;

                return true;
            }
        );
        $this->logFactory->method('create')->willReturn($log);

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage(withSymfonyMessage: true)),
            $this->createProceed($called)
        );

        $this->assertArrayNotHasKey('entity_type', $capturedExtra);
        $this->assertArrayNotHasKey('entity_id', $capturedExtra);
    }

    public function testEmailLogClearsRegistryEvenWhenLoggingDisabled(): void
    {
        // isEnabled() false -> emailLog() no-ops before ever creating a Log row. The registry
        // key must still be cleared, or it would leak onto the next email sent in this
        // request. Graph path + createBasicMessage() (not createMessage()/getSymfonyMessage())
        // to stay clear of the accepted < 2.4.8 baseline errors, same as the SMTP-2 tests above.
        $helper = $this->createMock(Data::class);
        $helper->method('versionCompare')->willReturn(true);
        $helper->method('isTestEmail')->willReturn(true);
        $helper->method('isEnabled')->willReturn(false);
        $helper->method('shouldUseGraphApi')->willReturn(true);
        $this->helper = $helper;
        $this->resourceMail = $this->enableLoggingResourceMail();
        $this->graphMailer->method('sendEmailPayload')->willReturn(true);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn (string $key) => $key === SetTemplateVarsEntity::REGISTRY_KEY
                ? ['entity_type' => 'order', 'entity_id' => 42]
                : ($key === 'mp_smtp_store_id' ? self::STORE_ID : null)
        );
        $registry->expects($this->once())->method('unregister')->with(SetTemplateVarsEntity::REGISTRY_KEY);
        $this->registry = $registry;

        $this->logFactory->expects($this->never())->method('create');

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($this->createBasicMessage()),
            $this->createProceed($called)
        );
    }

    public function testGetRecipientJoinsAddresses(): void
    {
        $message = $this->createMessage(new TextPart('x'), ['a@x.com', 'b@x.com']);

        $this->assertSame('a@x.com,b@x.com', $this->createSut()->getRecipient($message));
    }

    public function testValidateBlacklistSkipsTestEmail(): void
    {
        $this->helper->expects($this->never())->method('getBlacklist');

        $this->assertFalse(
            $this->createSut()->validateBlacklist($this->createMessage(new TextPart('x'), ['spam@x.com']))
        );
    }

    #[DataProvider('blacklistProvider')]
    public function testValidateBlacklistMatchesConfiguredPatterns(
        string $blacklist,
        string $recipient,
        bool $expected
    ): void {
        $helper = $this->createMock(Data::class);
        $helper->method('isTestEmail')->willReturn(false);
        $helper->method('getBlacklist')->willReturn($blacklist);
        $this->helper = $helper;

        $this->assertSame(
            $expected,
            $this->createSut()->validateBlacklist($this->createMessage(new TextPart('x'), [$recipient]))
        );
    }

    public static function blacklistProvider(): array
    {
        return [
            'matching pattern is rejected'   => ['/@spam\.com/', 'user@spam.com', true],
            'unlisted recipient passes'      => ['/@spam\.com/', 'user@good.com', false],
            'empty blacklist passes'         => ['', 'user@good.com', false],
            'invalid pattern is ignored'     => ['not-a-valid-regex', 'user@good.com', false],
        ];
    }

    // Pins on the Symfony hierarchy the fix depends on.

    public function testDataPartIsSubclassOfTextPart(): void
    {
        $this->assertTrue(is_subclass_of(DataPart::class, TextPart::class));
    }

    public function testAlternativePartIsNotATextPart(): void
    {
        $this->assertFalse(is_subclass_of(AlternativePart::class, TextPart::class));
    }

    public function testEmailIsARawMessage(): void
    {
        $this->assertTrue(is_subclass_of(Email::class, RawMessage::class));
    }
}
