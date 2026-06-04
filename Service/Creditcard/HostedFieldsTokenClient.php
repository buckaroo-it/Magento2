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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Creditcard;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

/**
 * Fetches OAuth access tokens for Hosted Fields (credit card) from Buckaroo's auth API.
 *
 * Extracted from the GetToken controller so the HTTP concern lives in a single,
 * testable place and the controller stays thin.
 */
class HostedFieldsTokenClient
{
    /**
     * Buckaroo OAuth token endpoint.
     */
    private const TOKEN_URL = 'https://auth.buckaroo.io/oauth/token';

    /**
     * @var Curl
     */
    private $curlClient;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param Curl $curlClient
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Curl $curlClient,
        BuckarooLoggerInterface $logger
    ) {
        $this->curlClient = $curlClient;
        $this->logger = $logger;
    }

    /**
     * Fetch an OAuth access token using the client-credentials grant.
     *
     * @param string $clientId
     * @param string $clientSecret
     *
     * @return array|null Decoded response payload, or null when it could not be decoded
     *
     * @throws LocalizedException
     */
    public function fetchToken(string $clientId, string $clientSecret): ?array
    {
        $response = $this->sendPostRequest(
            self::TOKEN_URL,
            $clientId,
            $clientSecret,
            [
                'scope'      => 'hostedfields:save',
                'grant_type' => 'client_credentials'
            ]
        );

        return json_decode($response, true);
    }

    /**
     * Send a POST request using Magento's Curl client.
     *
     * @param string $url
     * @param string $username
     * @param string $password
     * @param array $postData
     *
     * @return string
     *
     * @throws LocalizedException
     */
    private function sendPostRequest(string $url, string $username, string $password, array $postData): string
    {
        try {
            // Set Basic Auth credentials without base64_encode()
            $this->curlClient->setCredentials($username, $password);

            // Set the headers and post fields
            $this->curlClient->addHeader("Content-Type", "application/x-www-form-urlencoded");

            // Send the POST request
            $this->curlClient->post($url, http_build_query($postData));

            // Get the response body
            return $this->curlClient->getBody();
        } catch (\Exception $e) {
            $this->logger->addError('Curl request error: ' . $e->getMessage());
            throw new LocalizedException(
                __('Error occurred during cURL request: %1', $e->getMessage()),
                $e
            );
        }
    }
}
