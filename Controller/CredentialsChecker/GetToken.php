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
use Buckaroo\Magento2\Service\Creditcard\HostedFieldsTokenClient;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

class GetToken extends Action
{
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
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param BuckarooLoggerInterface $logger
     * @param Creditcards $configProviderCreditcard
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param HostedFieldsTokenClient $tokenClient
     * @param CheckoutSession $checkoutSession
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
        CheckoutSession $checkoutSession
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->configProviderCreditcard = $configProviderCreditcard;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        $this->tokenClient = $tokenClient;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
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
        $result = $this->resultJsonFactory->create();

        $requestOrigin = $this->getRequest()->getHeader('X-Requested-From');
        if ($requestOrigin !== 'MagentoFrontend' || !$this->checkoutSession->getQuoteId()) {
            return $result->setHttpResponseCode(403)->setData([
                'error' => true,
                'message' => 'Unauthorized request'
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
