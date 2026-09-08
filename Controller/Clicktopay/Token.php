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
use Buckaroo\Magento2\Service\OauthTokenService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;

class Token implements HttpPostActionInterface
{
    private const SCOPE = 'clicktopay:save';

    /**
     * Header the storefront sends so this proxy only serves genuine checkout AJAX requests.
     */
    private const FRONTEND_ORIGIN_HEADER = 'X-Requested-From';

    /**
     * Expected value of the frontend-origin header.
     */
    private const FRONTEND_ORIGIN_VALUE = 'MagentoFrontend';

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
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param ClicktopayConfig        $config
     * @param OauthTokenService       $tokenService
     * @param BuckarooLoggerInterface $logger
     * @param EncryptorInterface      $encryptor
     * @param Http                    $request
     * @param CheckoutSession         $checkoutSession
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        ClicktopayConfig $config,
        OauthTokenService $tokenService,
        BuckarooLoggerInterface $logger,
        EncryptorInterface $encryptor,
        Http $request,
        CheckoutSession $checkoutSession
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config            = $config;
        $this->tokenService      = $tokenService;
        $this->logger            = $logger;
        $this->encryptor         = $encryptor;
        $this->request           = $request;
        $this->checkoutSession   = $checkoutSession;
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
        // genuine storefront checkout request: reject anything without an active quote or a valid
        // form key. Without this an anonymous caller can mint OAuth tokens on the merchant's behalf.
        if (!$this->isTrustedCheckoutRequest()) {
            $this->logger->addError('[ClicktoPay] Token proxy: rejected untrusted request');
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(403);
            return $result->setData(['error' => 'Unauthorized request']);
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
     * Only serve genuine storefront checkout AJAX requests.
     *
     * Mirrors the hosted-fields token proxy (CredentialsChecker\GetToken): the caller must send
     * the storefront origin header and hold an active checkout quote. This keeps an anonymous,
     * session-less request from minting OAuth tokens on the merchant's credentials without relying
     * on a form key, which is unreliable for cached checkout AJAX.
     *
     * @return bool
     */
    private function isTrustedCheckoutRequest(): bool
    {
        return $this->request->getHeader(self::FRONTEND_ORIGIN_HEADER) === self::FRONTEND_ORIGIN_VALUE
            && (bool) $this->checkoutSession->getQuoteId();
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
