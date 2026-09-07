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

use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\GraphMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[CoversClass(GraphMailer::class)]
class GraphMailerTest extends TestCase
{
    private Curl&MockObject $curl;
    private Json&MockObject $json;
    private Data&MockObject $helper;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->curl   = $this->createMock(Curl::class);
        $this->json   = $this->createMock(Json::class);
        $this->helper = $this->createMock(Data::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Real JSON round-trip so the built Graph payload can be asserted on.
        $this->json->method('serialize')->willReturnCallback(static fn ($value): string => json_encode($value));
        $this->json->method('unserialize')->willReturnCallback(static fn (string $value) => json_decode($value, true));
    }

    private function createSut(): GraphMailer
    {
        return new GraphMailer($this->curl, $this->json, $this->helper, $this->logger);
    }

    private function sampleEmail(): Email
    {
        return (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Order confirmation')
            ->html('<p>Thank you</p>');
    }

    // $captured is bound by reference — populated once sendEmail() runs.
    private function captureSuccessfulPostPayload(array &$captured): void
    {
        $this->curl->method('post')->willReturnCallback(
            function ($url, $payload) use (&$captured): void {
                $captured['url']     = $url;
                $captured['payload'] = $payload;
            }
        );
        $this->curl->method('getStatus')->willReturn(202);
        $this->curl->method('getBody')->willReturn('');
    }


    public function testSendEmailThrowsWhenOauthTokenRetrievalFails(): void
    {
        $this->helper->method('getOauthAccessToken')->willThrowException(new \Exception('boom'));

        $this->logger->expects($this->once())->method('error')->with(
            'GraphMailer: OAuth2 token retrieval failed',
            ['error' => 'boom', 'storeId' => 5, 'exception' => \Exception::class]
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to get OAuth2 access token: boom');

        $this->createSut()->sendEmail($this->sampleEmail(), 5);
    }

    public function testSendEmailThrowsWhenAccessTokenIsEmpty(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('');

        $this->logger->expects($this->once())->method('error')->with('GraphMailer: OAuth2 token is empty');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to get OAuth2 access token for Microsoft Graph API.');

        $this->createSut()->sendEmail($this->sampleEmail());
    }

    public function testSendEmailThrowsWhenNoSenderAddress(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');

        $this->logger->expects($this->once())->method('error')->with('GraphMailer: No sender email address found');

        $email = (new Email())->to('recipient@example.com')->subject('Hi')->text('body');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No sender email address found.');

        $this->createSut()->sendEmail($email);
    }


    public function testSendEmailPostsGraphPayloadWithBearerTokenAndTimeout(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');

        $options = [];
        $this->curl->method('setOption')->willReturnCallback(
            function ($name, $value) use (&$options): void {
                $options[$name] = $value;
            }
        );
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(
            function (string $name, string $value) use (&$headers): void {
                $headers[$name] = $value;
            }
        );
        $captured = [];
        $this->captureSuccessfulPostPayload($captured);

        $this->createSut()->sendEmail($this->sampleEmail());

        $this->assertSame(30, $options[CURLOPT_TIMEOUT]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertSame('Bearer access-token', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertStringContainsString('sender%40example.com', $captured['url']);

        $decoded = json_decode($captured['payload'], true);
        $this->assertSame('Order confirmation', $decoded['message']['subject']);
        $this->assertSame('HTML', $decoded['message']['body']['contentType']);
        $this->assertSame('<p>Thank you</p>', $decoded['message']['body']['content']);
        $this->assertSame(
            'recipient@example.com',
            $decoded['message']['toRecipients'][0]['emailAddress']['address']
        );
        $this->assertArrayNotHasKey('name', $decoded['message']['toRecipients'][0]['emailAddress']);
        $this->assertFalse($decoded['saveToSentItems']);
    }

    public function testSendEmailSucceedsOnStatus200(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $this->curl->expects($this->once())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('');

        $this->createSut()->sendEmail($this->sampleEmail());
    }

    public function testSendEmailUsesTextContentTypeWhenNoHtmlBodyPresent(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $captured = [];
        $this->captureSuccessfulPostPayload($captured);

        $email = (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Plain')
            ->text('plain body');

        $this->createSut()->sendEmail($email);

        $decoded = json_decode($captured['payload'], true);
        $this->assertSame('Text', $decoded['message']['body']['contentType']);
        $this->assertSame('plain body', $decoded['message']['body']['content']);
    }

    public function testSendEmailIncludesRecipientAndReplyToNames(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $captured = [];
        $this->captureSuccessfulPostPayload($captured);

        $email = (new Email())
            ->from('sender@example.com')
            ->to(new Address('to@example.com', 'To Name'))
            ->cc(new Address('cc@example.com', 'Cc Name'))
            ->bcc(new Address('bcc@example.com', 'Bcc Name'))
            ->replyTo(new Address('reply@example.com', 'Reply Name'), new Address('noreply@example.com'))
            ->subject('Named')
            ->text('body');

        $this->createSut()->sendEmail($email);

        $decoded = json_decode($captured['payload'], true);
        $this->assertSame('To Name', $decoded['message']['toRecipients'][0]['emailAddress']['name']);
        $this->assertSame('Cc Name', $decoded['message']['ccRecipients'][0]['emailAddress']['name']);
        $this->assertSame('Bcc Name', $decoded['message']['bccRecipients'][0]['emailAddress']['name']);
        $this->assertSame('Reply Name', $decoded['message']['replyTo'][0]['emailAddress']['name']);
        // No name set — falls back to the address itself.
        $this->assertSame('noreply@example.com', $decoded['message']['replyTo'][1]['emailAddress']['name']);
    }

    public function testSendEmailEncodesAttachmentAsBase64FileAttachment(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $captured = [];
        $this->captureSuccessfulPostPayload($captured);

        $email = $this->sampleEmail()->attach('file content', 'file.txt', 'text/plain');

        $this->createSut()->sendEmail($email);

        $decoded    = json_decode($captured['payload'], true);
        $attachment = $decoded['message']['attachments'][0];
        $this->assertSame('#microsoft.graph.fileAttachment', $attachment['@odata.type']);
        $this->assertSame('file.txt', $attachment['name']);
        $this->assertSame('text/plain', $attachment['contentType']);
        $this->assertSame(base64_encode('file content'), $attachment['contentBytes']);
    }


    // sendEmailPayload(): the array-based entry point used by Transport::buildGraphPayload()
    // (Magento < 2.4.8, no egulias/email-validator installed). It must reuse the same token
    // retrieval + sendViaGraphApi() as sendEmail(), just skip the Symfony Email -> array step.

    private function samplePayload(): array
    {
        return [
            'message'         => [
                'subject'       => 'Order confirmation',
                'body'          => ['contentType' => 'HTML', 'content' => '<p>Thank you</p>'],
                'toRecipients'  => [['emailAddress' => ['address' => 'recipient@example.com']]],
                'ccRecipients'  => [],
                'bccRecipients' => [],
                'attachments'   => [],
            ],
            'saveToSentItems' => false,
        ];
    }

    public function testSendEmailPayloadThrowsWhenOauthTokenRetrievalFails(): void
    {
        $this->helper->method('getOauthAccessToken')->willThrowException(new \Exception('boom'));

        $this->logger->expects($this->once())->method('error')->with(
            'GraphMailer: OAuth2 token retrieval failed',
            ['error' => 'boom', 'storeId' => 5, 'exception' => \Exception::class]
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to get OAuth2 access token: boom');

        $this->createSut()->sendEmailPayload($this->samplePayload(), 'sender@example.com', 5);
    }

    public function testSendEmailPayloadThrowsWhenAccessTokenIsEmpty(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('');

        $this->logger->expects($this->once())->method('error')->with('GraphMailer: OAuth2 token is empty');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to get OAuth2 access token for Microsoft Graph API.');

        $this->createSut()->sendEmailPayload($this->samplePayload(), 'sender@example.com');
    }

    public function testSendEmailPayloadThrowsWhenSenderEmailMissing(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');

        $this->logger->expects($this->once())->method('error')->with('GraphMailer: No sender email address found');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No sender email address found.');

        $this->createSut()->sendEmailPayload($this->samplePayload(), null);
    }

    public function testSendEmailPayloadPostsPayloadUnchangedWithBearerTokenAndTimeout(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');

        $options = [];
        $this->curl->method('setOption')->willReturnCallback(
            function ($name, $value) use (&$options): void {
                $options[$name] = $value;
            }
        );
        $headers = [];
        $this->curl->method('addHeader')->willReturnCallback(
            function (string $name, string $value) use (&$headers): void {
                $headers[$name] = $value;
            }
        );
        $captured = [];
        $this->captureSuccessfulPostPayload($captured);

        $payload = $this->samplePayload();
        $this->createSut()->sendEmailPayload($payload, 'sender@example.com');

        $this->assertSame(30, $options[CURLOPT_TIMEOUT]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertSame('Bearer access-token', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertStringContainsString('sender%40example.com', $captured['url']);
        $this->assertSame($payload, json_decode($captured['payload'], true));
    }

    public function testSendEmailPayloadSucceedsOnStatus200(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $this->curl->expects($this->once())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('');

        $this->createSut()->sendEmailPayload($this->samplePayload(), 'sender@example.com');
    }

    public function testSendEmailPayloadThrowsOnApiErrorWithParseableBody(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $this->curl->method('getStatus')->willReturn(400);
        $this->curl->method('getBody')->willReturn('{"error":{"message":"Bad request"}}');

        $this->logger->expects($this->once())->method('error')->with(
            'GraphMailer: API Error',
            ['error' => 'Bad request']
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Microsoft Graph API email send failed (HTTP 400): Bad request');

        $this->createSut()->sendEmailPayload($this->samplePayload(), 'sender@example.com');
    }

    public function testSendEmailThrowsOnApiErrorWithParseableBody(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $this->curl->method('getStatus')->willReturn(400);
        $this->curl->method('getBody')->willReturn('{"error":{"message":"Bad request"}}');

        $this->logger->expects($this->once())->method('error')->with(
            'GraphMailer: API Error',
            ['error' => 'Bad request']
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Microsoft Graph API email send failed (HTTP 400): Bad request');

        $this->createSut()->sendEmail($this->sampleEmail());
    }

    public function testSendEmailLogsAndUsesRawBodyWhenErrorResponseIsUnparseable(): void
    {
        $this->helper->method('getOauthAccessToken')->willReturn('access-token');
        $this->curl->method('getStatus')->willReturn(400);
        $this->curl->method('getBody')->willReturn('not-json');

        // A fresh mock is required: a second method('unserialize') stub on the setUp()
        // instance would never win over the first-registered json_decode callback.
        $json = $this->createMock(Json::class);
        $json->method('serialize')->willReturnCallback(static fn ($value): string => json_encode($value));
        $json->method('unserialize')->willThrowException(new InvalidArgumentException('malformed'));
        $this->json = $json;

        $errorCalls = [];
        $this->logger->method('error')->willReturnCallback(
            function (string $message, array $context = []) use (&$errorCalls): void {
                $errorCalls[] = [$message, $context];
            }
        );

        $exception = null;
        try {
            $this->createSut()->sendEmail($this->sampleEmail());
        } catch (LocalizedException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(
            'Microsoft Graph API email send failed (HTTP 400): not-json',
            $exception->getMessage()
        );
        $this->assertCount(2, $errorCalls);
        $this->assertSame('GraphMailer: Failed to parse error response', $errorCalls[0][0]);
        $this->assertSame('not-json', $errorCalls[0][1]['response']);
        $this->assertSame(InvalidArgumentException::class, $errorCalls[0][1]['exception']);
        $this->assertSame('GraphMailer: API Error', $errorCalls[1][0]);
        $this->assertSame('not-json', $errorCalls[1][1]['error']);
    }
}
