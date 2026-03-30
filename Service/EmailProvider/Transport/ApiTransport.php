<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\Magento2\Service\EmailProvider\Transport;

use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as EmailProviderConfig;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Serialize\Serializer\Json;

class ApiTransport implements TransportInterface
{
    /**
     * @var EmailProviderConfig
     */
    protected $config;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param EmailProviderConfig $config
     * @param Log                 $logger
     * @param Curl                $curl
     * @param Json                $json
     */
    public function __construct(
        EmailProviderConfig $config,
        Log $logger,
        Curl $curl,
        Json $json
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->curl = $curl;
        $this->json = $json;
    }

    /**
     * Send email via REST API
     *
     * @param array $emailData
     * @param int|null $storeId
     *
     * @return array
     * @throws MailException
     */
    public function send(array $emailData, $storeId = null): array
    {
        try {
            $this->validateConfiguration($storeId);
            $requestData = $this->buildRequestData($emailData, $storeId);
            $this->configureCurlRequest($storeId);
            
            $jsonPayload = $this->json->serialize($requestData);
            $this->curl->post($this->config->getApiEndpoint($storeId), $jsonPayload);

            $statusCode = $this->curl->getStatus();
            $response = $this->curl->getBody();

            return $this->processResponse($statusCode, $response, $storeId);
        } catch (\Exception $e) {
            return $this->handleException($e, $storeId);
        }
    }

    /**
     * Validate API configuration
     *
     * @param int|null $storeId
     * @throws \InvalidArgumentException
     */
    private function validateConfiguration($storeId): void
    {
        $apiEndpoint = $this->config->getApiEndpoint($storeId);
        $apiKey = $this->config->getApiKey($storeId);

        if (empty($apiEndpoint)) {
            throw new \InvalidArgumentException('API endpoint is not configured');
        }

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('API key/token is not configured');
        }
    }

    /**
     * Build request payload
     *
     * @param array $emailData
     * @param int|null $storeId
     * @return array
     */
    private function buildRequestData(array $emailData, $storeId): array
    {
        $fromEmail = $this->config->getFromEmail($storeId) ?: $emailData['from_email'];
        $fromName = $this->config->getFromName($storeId) ?: $emailData['from_name'];
        $replyTo = $this->config->getReplyTo($storeId) ?: ($emailData['reply_to'] ?? null);

        $requestData = [
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName
            ],
            'to' => [
                [
                    'email' => $emailData['to_email'],
                    'name' => $emailData['to_name'] ?? ''
                ]
            ],
            'subject' => $emailData['subject'],
            'html' => $emailData['body_html'],
        ];

        if ($replyTo) {
            $requestData['reply_to'] = ['email' => $replyTo];
        }

        if (!empty($emailData['body_text'])) {
            $requestData['text'] = $emailData['body_text'];
        }

        if (!empty($emailData['headers']) && is_array($emailData['headers'])) {
            $requestData['headers'] = $emailData['headers'];
        }

        return $requestData;
    }

    /**
     * Configure cURL request with headers and authentication
     *
     * @param int|null $storeId
     */
    private function configureCurlRequest($storeId): void
    {
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->addHeader('Content-Type', 'application/json');

        $authType = $this->config->getApiAuthType($storeId);
        $apiKey = $this->config->getApiKey($storeId);

        $this->addAuthenticationHeader($authType, $apiKey);
    }

    /**
     * Add authentication header based on type
     *
     * @param string $authType
     * @param string $apiKey
     */
    private function addAuthenticationHeader($authType, $apiKey): void
    {
        switch ($authType) {
            case 'bearer':
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
                break;
            case 'api_key_header':
                $this->curl->addHeader('X-API-Key', $apiKey);
                break;
            case 'basic':
                $this->curl->addHeader('Authorization', 'Basic ' . base64_encode($apiKey));
                break;
            default:
                $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
        }
    }

    /**
     * Process API response
     *
     * @param int $statusCode
     * @param string $response
     * @param int|null $storeId
     * @return array
     * @throws \RuntimeException
     */
    private function processResponse($statusCode, $response, $storeId): array
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return $this->buildSuccessResponse($response, $storeId);
        }

        throw new \RuntimeException($this->parseErrorMessage($statusCode, $response));
    }

    /**
     * Build success response
     *
     * @param string $response
     * @param int|null $storeId
     * @return array
     */
    private function buildSuccessResponse($response, $storeId): array
    {
        $responseData = $this->parseJsonResponse($response);
        $providerName = $this->config->getProviderName($storeId);

        return [
            'success' => true,
            'message' => "Email sent via {$providerName} API",
            'message_id' => $responseData['id'] ?? $responseData['message_id'] ?? null,
            'response' => $responseData,
        ];
    }

    /**
     * Parse JSON response safely
     *
     * @param string $response
     * @return array
     */
    private function parseJsonResponse($response): array
    {
        try {
            return $this->json->unserialize($response);
        } catch (\Exception $e) {
            $this->logger->addDebug('Non-JSON API response: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse error message from response
     *
     * @param int $statusCode
     * @param string $response
     * @return string
     */
    private function parseErrorMessage($statusCode, $response): string
    {
        $errorMessage = "API error (HTTP {$statusCode})";

        try {
            $errorData = $this->json->unserialize($response);
            $errorMessage = $errorData['error']
                         ?? $errorData['message']
                         ?? $errorData['errors'][0]
                         ?? $errorMessage;
        } catch (\Exception $e) {
            $errorMessage .= ': ' . substr($response, 0, 200);
        }

        return $errorMessage;
    }

    /**
     * Handle exception and wrap in MailException
     *
     * @param \Exception $e
     * @param int|null $storeId
     * @throws MailException
     * @return never
     */
    private function handleException(\Exception $e, $storeId)
    {
        $providerName = $this->config->getProviderName($storeId) ?? 'External Provider';
        $errorMsg = "{$providerName} API error: " . $e->getMessage();
        $this->logger->addError($errorMsg);
        throw new MailException(__($errorMsg), $e);
    }
}
