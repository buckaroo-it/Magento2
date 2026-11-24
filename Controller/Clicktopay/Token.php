<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\Clicktopay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay as ClicktopayConfig;
use Buckaroo\Magento2\Service\CheckoutTokenRequestGuard;
use Buckaroo\Magento2\Service\OauthTokenService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;

class Token implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const SCOPE = 'clicktopay:save';

    private const RATE_LIMIT_SCOPE = 'clicktopay';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ClicktopayConfig
     */
    private ClicktopayConfig $config;

    /**
     * @var OauthTokenService
     */
    private OauthTokenService $tokenService;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var Http
     */
    private Http $request;

    /**
     * @var CheckoutTokenRequestGuard
     */
    private CheckoutTokenRequestGuard $requestGuard;

    /**
     * @param JsonFactory               $resultJsonFactory
     * @param ClicktopayConfig          $config
     * @param OauthTokenService         $tokenService
     * @param BuckarooLoggerInterface   $logger
     * @param EncryptorInterface        $encryptor
     * @param Http                      $request
     * @param CheckoutTokenRequestGuard $requestGuard
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        ClicktopayConfig $config,
        OauthTokenService $tokenService,
        BuckarooLoggerInterface $logger,
        EncryptorInterface $encryptor,
        Http $request,
        CheckoutTokenRequestGuard $requestGuard
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config            = $config;
        $this->tokenService      = $tokenService;
        $this->logger            = $logger;
        $this->encryptor         = $encryptor;
        $this->request           = $request;
        $this->requestGuard      = $requestGuard;
    }

    /**
     * Require a valid form key, including for AJAX requests.
     *
     * Magento's default CSRF check skips the form key for any request that sends
     * `X-Requested-With: XMLHttpRequest`, and any client can send that header.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->requestGuard->hasValidFormKey($request);
    }

    /**
     * Reply to a failed CSRF check with a JSON 403 instead of a redirect.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) Signature is fixed by CsrfAwareActionInterface
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->resultJsonFactory->create();
        $result->setHttpResponseCode(403);
        $result->setData(['error' => 'Unauthorized request']);

        return new InvalidRequestException($result);
    }

    /**
     * Proxy the Click to Pay OAuth client_credentials token request to avoid CORS.
     *
     * Token fetching and server-side caching live in OauthTokenService, shared
     * with the Hosted Fields token proxy.
     *
     * @return Json
     */
    public function execute(): Json
    {
        // This endpoint spends the merchant's Click to Pay credentials, so it must only serve a
        // genuine storefront checkout request: a POST with a valid session-bound form key from a
        // session holding an active quote. Without this an anonymous caller can mint OAuth tokens
        // on the merchant's behalf.
        if (!$this->requestGuard->isAuthorized($this->request)) {
            $this->logger->addDebug('[ClicktoPay] Token proxy: rejected untrusted request');
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(403);
            return $result->setData(['error' => 'Unauthorized request']);
        }

        if (!$this->requestGuard->consumeRateLimit(self::RATE_LIMIT_SCOPE)) {
            $this->logger->addDebug('[ClicktoPay] Token proxy: rate limit exceeded');
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(429);
            return $result->setData(['error' => 'Too many requests']);
        }

        // Both credentials are stored encrypted (obscure fields with the Encrypted backend
        // model), so scopeConfig returns ciphertext. Decrypt them before authenticating.
        $clientId     = $this->decryptCredential((string) $this->config->getClientId());
        $clientSecret = $this->decryptCredential((string) $this->config->getClientSecret());

        if ($clientId === '' || $clientSecret === '') {
            $this->logger->addError('[ClicktoPay] Token proxy: clientId or clientSecret not configured');
            return $this->errorResponse('Click to Pay credentials are not configured.');
        }

        $token = $this->tokenService->getToken($clientId, $clientSecret, self::SCOPE);
        if ($token === null) {
            return $this->errorResponse('Failed to obtain access token.');
        }

        $result = $this->resultJsonFactory->create();
        return $result->setData($token);
    }

    /**
     * Decrypt an encrypted credential value stored via the Encrypted backend model.
     *
     * @param string $value
     *
     * @return string
     */
    private function decryptCredential(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (string) $this->encryptor->decrypt($value);
        } catch (\Exception $e) {
            $this->logger->addError('[ClicktoPay] Token proxy: failed to decrypt credential: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Create a JSON error response with an HTTP 500 status code.
     *
     * @param string $message
     * @return Json
     */
    private function errorResponse(string $message): Json
    {
        $result = $this->resultJsonFactory->create();
        $result->setHttpResponseCode(500);
        return $result->setData(['error' => $message]);
    }
}
