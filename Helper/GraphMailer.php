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

namespace Mageplaza\Smtp\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Class GraphMailer
 * @package Mageplaza\Smtp\Helper
 */
class GraphMailer
{
    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * GraphMailer constructor.
     *
     * @param Curl $curl
     * @param Json $json
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Curl $curl,
        Json $json,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->curl   = $curl;
        $this->json   = $json;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Send email using Microsoft Graph API
     *
     * @param Email $email
     * @param int|null $storeId
     * @param array|null $configOverride
     *
     * @return void
     * @throws LocalizedException
     */
    public function sendEmail($email, $storeId = null, $configOverride = null)
    {
        // Get OAuth2 access token
        try {
            $accessToken = $this->helper->getOauthAccessToken($storeId, $configOverride);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->logger->error('GraphMailer: OAuth2 token retrieval failed', [
                'error'     => $errorMsg,
                'storeId'   => $storeId,
                'exception' => get_class($e)
            ]);
            throw new LocalizedException(__('Failed to get OAuth2 access token: %1', $errorMsg));
        }

        if (!$accessToken) {
            $this->logger->error('GraphMailer: OAuth2 token is empty');
            throw new LocalizedException(__('Failed to get OAuth2 access token for Microsoft Graph API.'));
        }

        // Get sender email (from address)
        $fromAddresses = $email->getFrom();
        if (empty($fromAddresses)) {
            $this->logger->error('GraphMailer: No sender email address found');
            throw new LocalizedException(__('No sender email address found.'));
        }
        $fromAddress = reset($fromAddresses);
        $senderEmail = $fromAddress->getAddress();

        // Build Graph API message payload
        $message = $this->buildGraphMessage($email);

        // Send via Microsoft Graph API
        $this->sendViaGraphApi($senderEmail, $message, $accessToken);
    }

    /**
     * Send a pre-built Microsoft Graph API message payload (array). Used by
     * Transport::buildGraphPayload() so the Graph path never has to construct a
     * Symfony\Component\Mime\Email/Address (Address requires egulias/email-validator,
     * which is not installed on Magento < 2.4.8). Reuses the same OAuth2 token
     * retrieval and sendViaGraphApi() as sendEmail().
     *
     * @param array $payload Same shape buildGraphMessage() produces.
     * @param string|null $senderEmail
     * @param int|null $storeId
     * @param array|null $configOverride
     *
     * @return void
     * @throws LocalizedException
     */
    public function sendEmailPayload($payload, $senderEmail, $storeId = null, $configOverride = null)
    {
        // Get OAuth2 access token
        try {
            $accessToken = $this->helper->getOauthAccessToken($storeId, $configOverride);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->logger->error('GraphMailer: OAuth2 token retrieval failed', [
                'error'     => $errorMsg,
                'storeId'   => $storeId,
                'exception' => get_class($e)
            ]);
            throw new LocalizedException(__('Failed to get OAuth2 access token: %1', $errorMsg));
        }

        if (!$accessToken) {
            $this->logger->error('GraphMailer: OAuth2 token is empty');
            throw new LocalizedException(__('Failed to get OAuth2 access token for Microsoft Graph API.'));
        }

        if (!$senderEmail) {
            $this->logger->error('GraphMailer: No sender email address found');
            throw new LocalizedException(__('No sender email address found.'));
        }

        // Send via Microsoft Graph API
        $this->sendViaGraphApi($senderEmail, $payload, $accessToken);
    }

    /**
     * Build Microsoft Graph API message format from Symfony Email
     *
     * @param Email $email
     *
     * @return array
     */
    private function buildGraphMessage(Email $email)
    {
        $message = [
            'message'         => [
                'subject'       => $email->getSubject(),
                'body'          => [
                    'contentType' => 'HTML',
                    'content'     => ''
                ],
                'toRecipients'  => [],
                'ccRecipients'  => [],
                'bccRecipients' => [],
                'attachments'   => []
            ],
            'saveToSentItems' => false
        ];

        // Set body content
        $htmlContent = $email->getHtmlBody();
        $textContent = $email->getTextBody();

        if ($htmlContent) {
            $message['message']['body']['contentType'] = 'HTML';
            $message['message']['body']['content']     = $htmlContent;
        } elseif ($textContent) {
            $message['message']['body']['contentType'] = 'Text';
            $message['message']['body']['content']     = $textContent;
        }

        // Set TO recipients
        foreach ($email->getTo() as $address) {
            $recipient = [
                'emailAddress' => [
                    'address' => $address->getAddress()
                ]
            ];
            if ($address->getName()) {
                $recipient['emailAddress']['name'] = $address->getName();
            }
            $message['message']['toRecipients'][] = $recipient;
        }

        // Set CC recipients
        foreach ($email->getCc() as $address) {
            $recipient = [
                'emailAddress' => [
                    'address' => $address->getAddress()
                ]
            ];
            if ($address->getName()) {
                $recipient['emailAddress']['name'] = $address->getName();
            }
            $message['message']['ccRecipients'][] = $recipient;
        }

        // Set BCC recipients
        foreach ($email->getBcc() as $address) {
            $recipient = [
                'emailAddress' => [
                    'address' => $address->getAddress()
                ]
            ];
            if ($address->getName()) {
                $recipient['emailAddress']['name'] = $address->getName();
            }
            $message['message']['bccRecipients'][] = $recipient;
        }

        // Set Reply-To
        if ($email->getReplyTo()) {
            $replyToAddresses = [];
            foreach ($email->getReplyTo() as $address) {
                $replyToAddresses[] = [
                    'emailAddress' => [
                        'address' => $address->getAddress(),
                        'name'    => $address->getName() ?: $address->getAddress()
                    ]
                ];
            }
            if (!empty($replyToAddresses)) {
                $message['message']['replyTo'] = $replyToAddresses;
            }
        }

        // Handle attachments
        $attachments = $email->getAttachments();
        foreach ($attachments as $attachment) {
            if ($attachment instanceof DataPart) {
                $message['message']['attachments'][] = [
                    '@odata.type'  => '#microsoft.graph.fileAttachment',
                    'name'         => $attachment->getFilename() ?: 'attachment',
                    'contentType'  => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                    'contentBytes' => base64_encode($attachment->getBody())
                ];
            }
        }

        return $message;
    }

    /**
     * Send email via Microsoft Graph API
     *
     * @param string $senderEmail
     * @param array $message
     * @param string $accessToken
     *
     * @return void
     * @throws LocalizedException
     */
    private function sendViaGraphApi($senderEmail, $message, $accessToken)
    {
        $url = sprintf('https://graph.microsoft.com/v1.0/users/%s/sendMail', urlencode($senderEmail));

        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->addHeader('Authorization', 'Bearer ' . $accessToken); // FIXED: Send full token
        $this->curl->addHeader('Content-Type', 'application/json');

        $payload = $this->json->serialize($message);

        $this->curl->post($url, $payload);

        $statusCode   = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();
        if ($statusCode !== 202 && $statusCode !== 200) {
            $errorMessage = $responseBody;
            try {
                $errorData = $this->json->unserialize($responseBody);
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                }
            } catch (\Exception $e) {
                $this->logger->error('GraphMailer: Failed to parse error response', [
                    'response'  => $responseBody,
                    'exception' => get_class($e)
                ]);
            }

            $this->logger->error('GraphMailer: API Error', ['error' => $errorMessage]);

            throw new LocalizedException(
                __('Microsoft Graph API email send failed (HTTP %1): %2', $statusCode, $errorMessage)
            );
        }
    }
}

