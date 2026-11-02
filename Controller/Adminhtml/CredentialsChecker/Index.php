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
declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\Adminhtml\CredentialsChecker;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Config\AdminConfigScope;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Validates the configured Buckaroo credentials from the admin configuration screen.
 *
 * This action relays merchant credentials to the Buckaroo API and must therefore never be
 * reachable from the storefront: it lives in the adminhtml area and is guarded by the
 * Buckaroo configuration ACL resource.
 */
class Index extends Action implements HttpPostActionInterface
{
    /**
     * ACL resource required to validate credentials.
     */
    public const ADMIN_RESOURCE = 'Buckaroo_Magento2::configuration';

    /**
     * Credential type for the secret key.
     */
    private const CREDENTIAL_SECRET_KEY = 'secretKey';

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var BuckarooAdapter
     */
    private $client;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var AdminConfigScope
     */
    private $adminConfigScope;

    /**
     * @param Context              $context
     * @param EncryptorInterface   $encryptor
     * @param BuckarooAdapter      $client
     * @param ScopeConfigInterface $scopeConfig
     * @param AdminConfigScope     $adminConfigScope
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        BuckarooAdapter $client,
        ScopeConfigInterface $scopeConfig,
        AdminConfigScope $adminConfigScope
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->client = $client;
        $this->scopeConfig = $scopeConfig;
        $this->adminConfigScope = $adminConfigScope;
    }

    /**
     * Check the Buckaroo secret key and merchant key
     *
     * @throws Exception
     *
     * @return Json
     */
    public function execute(): Json
    {
        $secretKey = (string)$this->getRequest()->getParam('secretKey', '');
        $merchantKey = (string)$this->getRequest()->getParam('merchantKey', '');

        if ($secretKey === '' || $merchantKey === '') {
            return $this->doResponse([
                'success' => false,
                'error_message' => __('Failed to start validation process due to lack of data')
            ]);
        }

        return $this->validateCredentials(
            $this->resolveCredential($merchantKey, 'merchantKey'),
            $this->resolveCredential($secretKey, self::CREDENTIAL_SECRET_KEY)
        );
    }

    /**
     * Resolves the provided credential by checking if it contains any non-asterisk characters.
     *
     * The configuration form renders stored encrypted values as asterisks, so an all-asterisk
     * input means "use the value already stored for this scope".
     *
     * @param string $credential The raw credential input.
     * @param string $type       The type of the credential ('secretKey' or 'merchantKey').
     *
     * @throws Exception
     *
     * @return string The resolved credential, either as provided or decrypted.
     */
    private function resolveCredential(string $credential, string $type): string
    {
        if (preg_match('/[^\*]/', $credential)) {
            return $credential;
        }

        // The stored value at exactly the scope being edited, which is what the form shows
        [$scopeType, $scopeId] = $this->adminConfigScope->getScope();
        $path = $type === self::CREDENTIAL_SECRET_KEY
            ? Account::XPATH_ACCOUNT_SECRET_KEY
            : Account::XPATH_ACCOUNT_MERCHANT_KEY;
        $storedCredential = $this->scopeConfig->getValue($path, $scopeType, $scopeId);

        return (string)$this->encryptor->decrypt((string)$storedCredential);
    }

    /**
     * Validates the credentials by sending them to the Buckaroo client for confirmation.
     *
     * @param string $merchantKey The merchant key to validate.
     * @param string $secretKey   The secret key to validate.
     *
     * @throws Exception
     *
     * @return Json The JSON response indicating whether the credentials are valid.
     */
    private function validateCredentials(string $merchantKey, string $secretKey): Json
    {
        if ($this->client->confirmCredential($merchantKey, $secretKey)) {
            return $this->doResponse(['success' => true]);
        }

        return $this->doResponse([
            'success' => false,
            'error_message' => __('The credentials are not valid!')
        ]);
    }

    /**
     * Set response on resultJson
     *
     * @param array $response
     *
     * @return Json
     */
    private function doResponse(array $response): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $result->setData($response);
    }
}
