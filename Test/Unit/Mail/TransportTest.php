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

    // Graph is the only path that still converts post-M2X-63 (SMTP is now a pass-through).
    private function convertViaGraph(?AbstractPart $body, ?EmailMessage $message = null): Email
    {
        $this->helper->method('shouldUseGraphApi')->willReturn(true);

        $captured = null;
        $this->graphMailer->method('sendEmail')->willReturnCallback(
            static function ($sent) use (&$captured) {
                $captured = $sent;

                return true;
            }
        );

        $called = false;
        $this->createSut()->aroundSendMessage(
            $this->createSubject($message ?? $this->createMessage($body)),
            $this->createProceed($called)
        );

        $this->assertInstanceOf(Email::class, $captured);

        return $captured;
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
        $email = $this->convertViaGraph(new TextPart('plain body'));

        $this->assertSame('plain body', $email->getTextBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertHtmlRootIsUnchanged(): void
    {
        $email = $this->convertViaGraph(new TextPart('<p>html body</p>', 'utf-8', 'html'));

        $this->assertSame('<p>html body</p>', $email->getHtmlBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertAlternativePartKeepsBothBodies(): void
    {
        $email = $this->convertViaGraph(
            new AlternativePart(new TextPart('plain'), new TextPart('<p>html</p>', 'utf-8', 'html'))
        );

        $this->assertSame('plain', $email->getTextBody());
        $this->assertSame('<p>html</p>', $email->getHtmlBody());
        $this->assertSame([], $email->getAttachments());
    }

    public function testConvertMixedAlternativeWithAttachmentKeepsBodyAndAttachment(): void
    {
        $email = $this->convertViaGraph(
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
        $email = $this->convertViaGraph(
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
        $email = $this->convertViaGraph(
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
        $email = $this->convertViaGraph(
            new MixedPart(new TextPart('plain'), new DataPart($payload, 'blob.pdf', 'application/pdf'))
        );

        $this->assertSame(hash('sha256', $payload), hash('sha256', $email->getAttachments()[0]->getBody()));
    }

    public function testConvertUtf8BodyIsByteIdentical(): void
    {
        $body = 'Xin chào — tiếng Việt có dấu';
        $email = $this->convertViaGraph(
            new MixedPart(new TextPart($body), new DataPart('X', 'a.txt', 'text/plain'))
        );

        $this->assertSame($body, $email->getTextBody());
    }

    public function testConvertBareDataPartRootBecomesAttachmentNotBody(): void
    {
        $email = $this->convertViaGraph(new DataPart('FILEDATA', 'only.pdf', 'application/pdf'));

        $this->assertSame(['only.pdf'], $this->attachmentNames($email));
        $this->assertNull($email->getTextBody());
        $this->assertNull($email->getHtmlBody());
    }

    public function testConvertHtmlDataPartRootIsNotUsedAsBody(): void
    {
        $email = $this->convertViaGraph(new DataPart('<h1>file</h1>', 'page.html', 'text/html'));

        $this->assertSame(['page.html'], $this->attachmentNames($email));
        $this->assertNull($email->getHtmlBody());
    }

    public function testConvertTextPlainAttachmentDoesNotOverwriteHtmlBody(): void
    {
        // DataPart extends TextPart, so an attachment-first tree used to hijack the body.
        $email = $this->convertViaGraph(
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

        $email = $this->convertViaGraph($smime);

        $this->assertSame($smime, $email->getBody());
    }

    public function testConvertPreservesNonUtf8Charset(): void
    {
        $email = $this->convertViaGraph(new TextPart('body', 'iso-8859-1', 'html'));

        $this->assertSame('iso-8859-1', $email->getHtmlCharset());
    }

    public function testConvertAttachmentsOnlyDoesNotInjectPlaceholder(): void
    {
        $email = $this->convertViaGraph(
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
        $email = $this->convertViaGraph(
            new MixedPart(new TextPart('plain body'), new TextPart('BEGIN:VCALENDAR', 'utf-8', 'calendar'))
        );

        $this->assertSame('plain body', $email->getTextBody());
        $this->assertCount(1, $email->getAttachments());
    }

    public function testConvertFallsBackToUtf8WhenContentTypeIsNotParameterized(): void
    {
        $part = new TextPart('body');
        $part->getHeaders()->addTextHeader('Content-Type', 'text/plain');

        $email = $this->convertViaGraph($part);

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

        $email = $this->convertViaGraph(null, $message);

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

        $email = $this->convertViaGraph(null, $message);

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

        $email = $this->convertViaGraph(null, $message);

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

        $this->assertCount(2, $this->convertViaGraph(null, $message)->getReplyTo());
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

        $this->assertCount(1, $this->convertViaGraph(null, $message)->getCc());
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
