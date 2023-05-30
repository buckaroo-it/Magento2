<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\HTTP\Client\Curl;

class Json
{
    /**
     * @var Curl
     */
    private $client;

    /**
     * @var Log
     */
    private $logger;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $websiteKey;

    /**
     * @param Curl $client
     * @param Log $logger
     */
    public function __construct(
        Curl $client,
        Log $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create post request to payment engine
     *
     * @param array $data
     * @param string|int $mode
     * @return false|mixed
     */
    public function doRequest(array $data, $mode)
    {
        $urls = $this->getUrls($mode);

        $this->logger->addDebug(__METHOD__ . '|5|' . var_export($data, true));

        $options = $this->getOptions($urls['uri'], $urls['uri2'], $data, 'POST');
        $this->client->setOptions($options);

        return $this->getResponse($urls['uri'], $data);
    }

    /**
     * Create cancel request to payment engine
     *
     * @param string $key
     * @param int $mode
     * @return false|mixed
     */
    public function doCancelRequest($key, $mode)
    {
        $urls = $this->getUrls($mode, 'cancel', $key);

        $options = $this->getOptions($urls['uri'], $urls['uri2'], [], 'GET');
        $this->client->setOptions($options);

        return $this->getResponse($urls['uri']);
    }

    /**
     * Create a status request on transaction by transaction_id
     *
     * @param string $transactionId
     * @param int $mode
     * @return void
     */
    public function doStatusRequest($transactionId, $mode)
    {
        $urls = $this->getUrls($mode, 'status', $transactionId);

        $options = $this->getOptions($urls['uri'], $urls['uri2'], [], 'GET');
        $this->client->setOptions($options);

        $this->logger->addDebug(__METHOD__ . '|10|' . var_export($options, true));

        return $this->getResponse($urls['uri']);
    }

    /**
     * Get CURL options
     *
     * @param string $uri
     * @param string $uri2
     * @param array $data
     * @param string $httpMethod
     * @return array
     */
    public function getOptions(string $uri, string $uri2, array $data, string $httpMethod): array
    {
        $timeStamp = time();
        $nonce = $this->stringRandom();
        $json = \json_encode($data, JSON_PRETTY_PRINT);
        // phpcs:disable
        $md5 = md5($json, true);
        // phpcs:enable
        $encodedContent = base64_encode($md5);

        $rawData = $this->websiteKey . $httpMethod . $uri2 . $timeStamp . $nonce . $encodedContent;
        $hash = hash_hmac('sha256', $rawData, $this->secretKey, true);
        $hmac = base64_encode($hash);

        $hmacFull = $this->websiteKey . ':' . $hmac . ':' . $nonce . ':' . $timeStamp;

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: hmac ' . $hmacFull,
        ];

        return [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Magento2',
            CURLOPT_URL => $uri,
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_POSTFIELDS => $json
        ];
    }

    /**
     * Get random string
     *
     * @param int $length
     * @return string
     */
    private function stringRandom($length = 16)
    {
        $chars = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
        $str = "";

        for ($i = 0; $i < $length; $i++) {
            $key = array_rand($chars);
            $str .= $chars[$key];
        }

        return $str;
    }

    /**
     * Get Client status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->client->getStatus();
    }

    /**
     * Set Buckaroo Secret Key
     *
     * @param string $secretKey
     * @return void
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Set Merchant Key
     *
     * @param string $websiteKey
     * @return void
     */
    public function setWebsiteKey($websiteKey)
    {
        $this->websiteKey = $websiteKey;
    }

    /**
     * Get Response after JSON request
     *
     * @param string $uri
     * @param array $data
     * @return false|mixed
     */
    public function getResponse(string $uri, array $data = [])
    {
        try {
            if (!empty($data)) {
                $this->client->post($uri, $data);
            } else {
                $this->client->get($uri);
            }

            $response = json_decode($this->client->getBody(), true);
        } catch (\Exception $e) {
            $this->logger->addDebug(__METHOD__ . '|10|' . var_export($e->getMessage(), true));
            return false;
        }

        $this->logger->addDebug(__METHOD__ . '|15|' . var_export(
            [
                    $response,
                    $this->client->getStatus(),
                ],
            true
        ));

        return $response;
    }

    /**
     * Get URLs by mode and action
     *
     * @param string|int $mode
     * @param string $action
     * @param string $transactionId
     * @return array
     */
    private function getUrls($mode, string $action = '', string $transactionId = ''): array
    {

        $url = ($mode == Data::MODE_LIVE) ? 'checkout.buckaroo.nl' : 'testcheckout.buckaroo.nl';
        $url .= '/json/Transaction';

        if (!empty($action)) {
            $url .= '/' . $action;
        }

        if (!empty($transactionId)) {
            $url .= '/' . $transactionId;
        }

        return [
            'uri' => 'https://' . $url,
            'uri2' => strtolower(rawurlencode($url))
        ];
    }
}
