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

        // Set the URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set the HTTP method to POST
        curl_setopt($ch, CURLOPT_POST, true);

        // Set the username and password for Basic Auth
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

        // Set the Content-Type to application/x-www-form-urlencoded
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

        $requestOrigin = $this->getRequest()->getHeader('X-Requested-From');

        if ($requestOrigin !== 'MagentoFrontend') {
            return $result->setHttpResponseCode(403)->setData(['error' => 'Unauthorized request']);
        }

        $hostedFieldsUsername = $this->getHostedFieldsUsername();
        $hostedFieldsPassword = $this->getHostedFieldsPassword();

        if (!empty($hostedFieldsUsername) && !empty($hostedFieldsPassword)) {
            try {
                $url = "https://auth.buckaroo.io/oauth/token";
                $postData = [
                    'scope' => 'hostedfields:save',
                    'grant_type' => 'client_credentials'
                ];

                $response = $this->sendPostRequest($url, $hostedFieldsUsername, $hostedFieldsPassword, $postData);
                $responseArray = json_decode($response, true);

                if (isset($responseArray['access_token'])) {
                    return $result->setData($responseArray);
                }

                return $result->setHttpResponseCode(500)->setData([
                    'error' => 'Unable to fetch token',
                    'response' => $response
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Error occurred while fetching token: ' . $e->getMessage());
                return $result->setHttpResponseCode(500)->setData([
                    'error' => 'An error occurred while fetching the token',
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            return $result->setHttpResponseCode(400)->setData([
                'error' => 'Hosted Fields Username or Password is empty.'
            ]);
        }
    }
}
