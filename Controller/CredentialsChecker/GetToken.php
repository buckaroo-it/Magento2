<?php
namespace Buckaroo\Magento2\Controller\CredentialsChecker;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

class GetToken extends Action
{
    protected $resultJsonFactory;
    protected $logger;
    protected $configProviderAccount;
    protected $encryptor;
    protected $store;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Account $configProviderAccount,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        parent::__construct($context);
    }

    private function sendPostRequest($url, $username, $password, $postData) {
        // Initialize cURL
        $ch = curl_init();

        // Set the URL and method
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);

        // Basic Auth and headers
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        // Set the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        // Return the response instead of printing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the request
        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            $error = 'Curl error: ' . curl_error($ch);
            curl_close($ch);
            throw new \Exception($error);
        }

        // Close the cURL session
        curl_close($ch);
        return $response;
    }

    protected function getHostedFieldsUsername()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderAccount->getHostedFieldsUsername($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->error('Error decrypting Hosted Fields Username: ' . $e->getMessage());
            return null;
        }
    }

    protected function getHostedFieldsPassword()
    {
        try {
            return $this->encryptor->decrypt(
                $this->configProviderAccount->getHostedFieldsPassword($this->store)
            );
        } catch (\Exception $e) {
            $this->logger->error('Error decrypting Hosted Fields Password: ' . $e->getMessage());
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
        $hostedFieldsUsername = $this->getHostedFieldsUsername();
        $hostedFieldsPassword = $this->getHostedFieldsPassword();

        if (empty($hostedFieldsUsername) || empty($hostedFieldsPassword)) {
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'Hosted Fields Username or Password is empty.'
            ]);
        }

        // Try to fetch the token
        try {
            $url = "https://auth.buckaroo.io/oauth/token";
            $postData = [
                'scope' => 'hostedfields:save',
                'grant_type' => 'client_credentials'
            ];

            $response = $this->sendPostRequest($url, $hostedFieldsUsername, $hostedFieldsPassword, $postData);
            $responseArray = json_decode($response, true);

            // Check for successful response
            if (isset($responseArray['access_token'])) {
                return $result->setData([
                    'error' => false,
                    'data' => $responseArray
                ]);
            }

            // Handle error response
            $message = isset($responseArray['message']) ? $responseArray['message'] : 'Unknown error occurred';
            return $result->setHttpResponseCode(400)->setData([
                'error' => true,
                'message' => 'Error fetching token: ' . $message
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error occurred while fetching token: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => 'An error occurred while fetching the token: ' . $e->getMessage()
            ]);
        }
    }
}
