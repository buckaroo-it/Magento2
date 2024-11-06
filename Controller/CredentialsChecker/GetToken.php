<?php
namespace Buckaroo\Magento2\Controller\CredentialsChecker;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\HTTP\Client\Curl;

class GetToken extends Action
{
    protected $resultJsonFactory;
    protected $logger;
    protected $configProviderCreditcard;
    protected $encryptor;
    protected $store;
    protected $curlClient;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Creditcards $configProviderCreditcard,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        Curl $curlClient
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->configProviderCreditcard = $configProviderCreditcard;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        $this->curlClient = $curlClient;
        parent::__construct($context);
    }

    /**
     * Send POST request using Magento's Curl client.
     */
    private function sendPostRequest($url, $username, $password, $postData) {
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
            $this->logger->error('Curl request error: ' . $e->getMessage());
            throw new \Exception('Error occurred during cURL request: ' . $e->getMessage());
        }
    }

    protected function getHostedFieldsClientId()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderCreditcard->getHostedFieldsClientId($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->error('Error decrypting Hosted Fields fields: ' . $e->getMessage());
            return null;
        }
    }

    protected function getHostedFieldsClientSecret()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderCreditcard->getHostedFieldsClientSecret($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->error('Error decrypting Hosted Fields fields: ' . $e->getMessage());
            return null;
        }
    }

    protected function getAllowedIssuers()
    {
        try {
            return $this->configProviderCreditcard->getSupportedServices();
        } catch (\Exception $e) {
            $this->logger->error('Error getting Allowed Issuers: ' . $e->getMessage());
            return null;
        }
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // Validate the request origin
        $requestOrigin = $this->getRequest()->getHeader('X-Requested-From');
        if ($requestOrigin !== 'MagentoFrontend') {
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
            $url = "https://auth.buckaroo.io/oauth/token";
            $postData = [
                'scope' => 'hostedfields:save',
                'grant_type' => 'client_credentials'
            ];

            $response = $this->sendPostRequest($url, $hostedFieldsClientId, $hostedFieldsClientSecret, $postData);
            $responseArray = json_decode($response, true);

            // Check for successful response
            if (isset($responseArray['access_token'])) {
                return $result->setData([
                    'error' => false,
                    'data' => [
                        'access_token' => $responseArray['access_token'],
                        'issuers' => $issuers
                    ]
                ]);
            }

            // Handle error response
            $message = isset($responseArray['message']) ? $responseArray['message'] : 'Unknown error occurred';
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'Error fetching token.'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error occurred while fetching token.');
            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => 'An error occurred while fetching the token.'
            ]);
        }
    }
}
