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

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\HTTP\Client\Curl;

class Json
{
    private $client;
    private $logger;

    private $secretKey;
    private $websiteKey;

    public function __construct(
        Curl $client,
        Log $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function doRequest(array $data, $mode)
    {
        $url  = $mode == \Buckaroo\Magento2\Helper\Data::MODE_LIVE ?
            'checkout.buckaroo.nl' : 'testcheckout.buckaroo.nl';
        $uri  = 'https://' . $url . '/json/Transaction';
        $uri2 = strtolower(rawurlencode($url . '/json/Transaction'));

        $this->logger->addDebug(__METHOD__ . '|5|' . var_export($data, true));

        $options = $this->getOptions($uri, $uri2, $data, 'POST');

        $this->client->setOptions($options);

        try {
            $this->client->post($uri, $data);
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

    public function doCancelRequest($key, $mode)
    {
        $url  = $mode == \Buckaroo\Magento2\Helper\Data::MODE_LIVE ?
            'checkout.buckaroo.nl' : 'testcheckout.buckaroo.nl';
        $uri  = 'https://' . $url . '/json/Transaction/cancel/' . $key;
        $uri2 = strtolower(rawurlencode($url . '/json/Transaction/cancel/' . $key));

        $options = $this->getOptions($uri, $uri2, [], 'GET');
        $this->client->setOptions($options);

        try {
            $this->client->get($uri);
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

    public function getOptions($uri, $uri2, $data, $httpMethod)
    {
        $timeStamp = time();
        $nonce     = $this->stringRandom();
        $json      = json_encode($data, JSON_PRETTY_PRINT);
        // phpcs:disable
        $md5 = md5($json, true);
        // phpcs:enable
        $encodedContent = base64_encode($md5);

        $rawData = $this->websiteKey . $httpMethod . $uri2 . $timeStamp . $nonce . $encodedContent;
        $hash    = hash_hmac('sha256', $rawData, $this->secretKey, true);
        $hmac    = base64_encode($hash);

        $hmac_full = $this->websiteKey . ':' . $hmac . ':' . $nonce . ':' . $timeStamp;

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: hmac ' . $hmac_full,
        ];

        return [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'Magento2',
            CURLOPT_URL            => $uri,
            CURLOPT_CUSTOMREQUEST  => $httpMethod,
            CURLOPT_POSTFIELDS     => $json,
            //ZAK
            //CURLOPT_SSL_VERIFYHOST => 0,
            //CURLOPT_SSL_VERIFYPEER => 0
        ];
    }

    public function getStatus()
    {
        return $this->client->getStatus();
    }

    private function stringRandom($length = 16)
    {
        $chars = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
        $str   = "";

        for ($i = 0; $i < $length; $i++) {
            $key = array_rand($chars);
            $str .= $chars[$key];
        }

        return $str;
    }

    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function setWebsiteKey($websiteKey)
    {
        $this->websiteKey = $websiteKey;
    }
}
