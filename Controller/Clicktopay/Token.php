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

namespace Buckaroo\Magento2\Controller\Clicktopay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay as ClicktopayConfig;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;

class Token implements HttpPostActionInterface
{
    private const AUTH_ENDPOINT = 'https://auth.buckaroo.io/oauth/token';
    private const SCOPE         = 'clicktopay:save';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ClicktopayConfig
     */
    private ClicktopayConfig $config;

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param RequestInterface        $request
     * @param ClicktopayConfig        $config
     * @param Curl                    $curl
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        ClicktopayConfig $config,
        Curl $curl,
        BuckarooLoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $request;
        $this->config            = $config;
        $this->curl              = $curl;
        $this->logger            = $logger;
    }

    /**
     * Proxy the Click to Pay OAuth client_credentials token request to avoid CORS.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $clientId     = (string) $this->config->getClientId();
        $clientSecret = (string) $this->config->getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            $this->logger->addError('[ClicktoPay] Token proxy: clientId or clientSecret not configured');
            return $this->errorResponse('Click to Pay credentials are not configured.');
        }

        try {
            $credentials = base64_encode($clientId . ':' . $clientSecret);

            $this->curl->setHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ]);

            $this->curl->post(
                self::AUTH_ENDPOINT,
                http_build_query([
                    'grant_type' => 'client_credentials',
                    'scope'      => self::SCOPE,
                ])
            );

            $body = $this->curl->getBody();
            $data = json_decode($body, true);

            if (!isset($data['access_token'])) {
                $this->logger->addError('[ClicktoPay] Token proxy: unexpected response', ['body' => $body]);
                return $this->errorResponse('Failed to obtain access token.');
            }

            $result = $this->resultJsonFactory->create();
            return $result->setData(['access_token' => $data['access_token']]);
        } catch (\Exception $e) {
            $this->logger->addError('[ClicktoPay] Token proxy exception: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while fetching the access token.');
        }
    }

    /**
     * @param string $message
     *
     * @return Json
     */
    private function errorResponse(string $message): Json
    {
        $result = $this->resultJsonFactory->create();
        $result->setHttpResponseCode(500);
        return $result->setData(['error' => $message]);
    }
}
