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

namespace Buckaroo\Magento2\Controller\CredentialsChecker;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards;
use Buckaroo\Magento2\Service\CheckoutTokenRequestGuard;
use Buckaroo\Magento2\Service\Creditcard\HostedFieldsTokenClient;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Storefront proxy that mints a Hosted Fields OAuth token for the checkout.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetToken extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const RATE_LIMIT_SCOPE = 'hostedfields';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var Creditcards
     */
    protected $configProviderCreditcard;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var HostedFieldsTokenClient
     */
    protected $tokenClient;

    /**
     * @var CheckoutTokenRequestGuard
     */
    protected $requestGuard;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param BuckarooLoggerInterface $logger
     * @param Creditcards $configProviderCreditcard
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param HostedFieldsTokenClient $tokenClient
     * @param CheckoutTokenRequestGuard $requestGuard
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        BuckarooLoggerInterface $logger,
        Creditcards $configProviderCreditcard,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        HostedFieldsTokenClient $tokenClient,
        CheckoutTokenRequestGuard $requestGuard
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->configProviderCreditcard = $configProviderCreditcard;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        $this->tokenClient = $tokenClient;
        $this->requestGuard = $requestGuard;
        parent::__construct($context);
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
        return new InvalidRequestException($this->unauthorizedResponse());
    }

    /**
     * Build the JSON 403 response for a rejected request.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function unauthorizedResponse()
    {
        return $this->resultJsonFactory->create()->setHttpResponseCode(403)->setData([
            'error' => true,
            'message' => 'Unauthorized request'
        ]);
    }

    /**
     * Retrieve the Hosted Fields client ID.
     *
     * @return string|null
     */
    protected function getHostedFieldsClientId()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderCreditcard->getHostedFieldsClientId($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->addError('Error decrypting Hosted Fields fields: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve the Hosted Fields client secret.
     *
     * @return string|null
     */
    protected function getHostedFieldsClientSecret()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderCreditcard->getHostedFieldsClientSecret($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->addError('Error decrypting Hosted Fields fields: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve the list of allowed issuers.
     *
     * @return array|null
     */
    protected function getAllowedIssuers()
    {
        try {
            return $this->configProviderCreditcard->getSupportedServices();
        } catch (\Exception $e) {
            $this->logger->addError('Error getting Allowed Issuers: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Return a token payload for the hosted fields frontend.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        // This endpoint mints tokens on the merchant's Hosted Fields credentials, so it only
        // serves a POST with a valid session-bound form key from a session that holds an active
        // checkout quote, and it is rate limited per session and per IP.
        if (!$this->requestGuard->isAuthorized($this->getRequest())) {
            $this->logger->addDebug('[Hosted Fields] Token proxy: rejected untrusted request');
            return $this->unauthorizedResponse();
        }

        $result = $this->resultJsonFactory->create();

        if (!$this->requestGuard->consumeRateLimit(self::RATE_LIMIT_SCOPE)) {
            $this->logger->addDebug('[Hosted Fields] Token proxy: rate limit exceeded');
            return $result->setHttpResponseCode(429)->setData([
                'error' => true,
                'message' => 'Too many requests'
            ]);
        }

        // Get username and password
        $hostedFieldsClientId = $this->getHostedFieldsClientId();
        $hostedFieldsClientSecret = $this->getHostedFieldsClientSecret();
        $issuers = $this->getAllowedIssuers();

        if (empty($hostedFieldsClientId) || empty($hostedFieldsClientSecret)) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'Hosted Fields Username or Password is empty.'
            ]);
        }
        if (empty($issuers)) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'There is no Allowed Issuers for Hosted Fields.'
            ]);
        }

        // Try to fetch the token
        try {
            $responseArray = $this->tokenClient->fetchToken(
                $hostedFieldsClientId,
                $hostedFieldsClientSecret
            );

            // Check for successful response and include expires_in if available
            if (isset($responseArray['access_token'])) {
                return $result->setData([
                    'error' => false,
                    'data' => [
                        'access_token' => $responseArray['access_token'],
                        'expires_in'   => $responseArray['expires_in'],
                        'issuers'      => $issuers
                    ]
                ]);
            }

            // Handle error response
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'Error fetching token.'
            ]);

        } catch (\Exception $e) {
            $this->logger->addError('Error occurred while fetching token.');
            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => 'An error occurred while fetching the token.'
            ]);
        }
    }
}
