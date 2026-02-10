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
            $apiEndpoint = $this->config->getApiEndpoint($storeId);
            $apiKey = $this->config->getApiKey($storeId);
            $authType = $this->config->getApiAuthType($storeId);
            $providerName = $this->config->getProviderName($storeId);

            if (empty($apiEndpoint)) {
                throw new \Exception('API endpoint is not configured');
            }

            if (empty($apiKey)) {
                throw new \Exception('API key/token is not configured');
            }

            $this->logger->addDebug("External Email Provider ({$providerName}) API: Preparing to send email", [
                'to' => $emailData['to_email'],
                'subject' => $emailData['subject'],
                'endpoint' => $apiEndpoint
            ]);

            // Prepare API request data (generic structure that works for most providers)
            $fromEmail = $this->config->getFromEmail($storeId) ?: $emailData['from_email'];
            $fromName = $this->config->getFromName($storeId) ?: $emailData['from_name'];
            $replyTo = $this->config->getReplyTo($storeId) ?: ($emailData['reply_to'] ?? null);

            // Build request payload (generic format - can be customized by merchant)
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

            // Add optional fields
            if ($replyTo) {
                $requestData['reply_to'] = ['email' => $replyTo];
            }

            if (!empty($emailData['body_text'])) {
                $requestData['text'] = $emailData['body_text'];
            }

            // Add custom headers if provided
            if (!empty($emailData['headers']) && is_array($emailData['headers'])) {
                $requestData['headers'] = $emailData['headers'];
            }

            // Configure cURL request
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 30);
            $this->curl->addHeader('Content-Type', 'application/json');

            // Add authentication header based on type
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
                    // Bearer as default
                    $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            }

            $jsonPayload = $this->json->serialize($requestData);

            $this->logger->addDebug("External Email Provider ({$providerName}) API: Sending request", [
                'endpoint' => $apiEndpoint,
                'payload_size' => strlen($jsonPayload),
                'auth_type' => $authType
            ]);

            // Send request
            $this->curl->post($apiEndpoint, $jsonPayload);

            $statusCode = $this->curl->getStatus();
            $response = $this->curl->getBody();

            $this->logger->addDebug("External Email Provider ({$providerName}) API: Response received", [
                'status_code' => $statusCode,
                'response' => substr($response, 0, 500) // Log first 500 chars
            ]);

            // Parse response
            if ($statusCode >= 200 && $statusCode < 300) {
                $responseData = [];
                try {
                    $responseData = $this->json->unserialize($response);
                } catch (\Exception $e) {
                    // Some APIs return non-JSON success responses
                    $this->logger->addDebug("API response is not JSON, treating as success");
                }

                return [
                    'success' => true,
                    'message' => "Email sent successfully via {$providerName} API",
                    'message_id' => $responseData['id'] ?? $responseData['message_id'] ?? null,
                    'response' => $responseData,
                ];
            } else {
                // Handle error response
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

                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            $providerName = $this->config->getProviderName($storeId) ?? 'External Provider';
            $errorMsg = "External Email Provider ({$providerName}) API error: " . $e->getMessage();
            
            $this->logger->addError($errorMsg, [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw new MailException(__($errorMsg), $e);
        }
    }
}
