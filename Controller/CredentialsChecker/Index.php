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

namespace Buckaroo\Magento2\Controller\CredentialsChecker;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\Encryptor;

class Index extends Action implements HttpPostActionInterface
{
    /**
     * @var ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var Encryptor
     */
    private $encryptor;

    /**
     * @var Account
     */
    private $configProviderAccount;

    /**
     * @var BuckarooAdapter
     */
    private $client;

    /**
     * Check Credentials in Admin
     *
     * @param Context $context
     * @param Factory $configProviderFactory
     * @param Encryptor $encryptor
     * @param Account $configProviderAccount
     * @param BuckarooAdapter $client
     * @throws BuckarooException
     */
    public function __construct(
        Context $context,
        Factory $configProviderFactory,
        Encryptor $encryptor,
        Account $configProviderAccount,
        BuckarooAdapter $client
    ) {
        parent::__construct($context);
        $this->accountConfig = $configProviderFactory->get('account');
        $this->encryptor = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->client = $client;
    }

    /**
     * Check Buckaroo Credentials Secret Key and Merchant Key
     *
     * @return Json
     * @throws \Exception
     */
    public function execute(): Json
    {
        $params = $this->getRequest()->getParams();
        if (empty($params) || empty($params['secretKey']) || empty($params['merchantKey'])) {
            return $this->doResponse([
                'success' => false,
                'error_message' => __('Failed to start validation process due to lack of data')
            ]);
        }

        $secretKey = $this->resolveCredential($params['secretKey'], 'secretKey');
        $merchantKey = $this->resolveCredential($params['merchantKey'], 'merchantKey');

        return $this->validateCredentials($merchantKey, $secretKey);
    }

    /**
     * Resolves the provided credential by checking if it contains any non-asterisk characters.
     * If it contains any real characters, the raw credential is returned.
     * Otherwise, the credential is decrypted from the stored configuration.
     *
     * @param string $credential The raw credential input.
     * @param string $type The type of the credential ('secretKey' or 'merchantKey').
     * @return string The resolved credential, either as provided or decrypted.
     * @throws \Exception
     */
    private function resolveCredential(string $credential, string $type): string
    {
        return preg_match('/[^\*]/', $credential) ? $credential :
            $this->encryptor->decrypt($this->configProviderAccount->{"get{$type}"}());
    }

    /**
     * Validates the credentials by sending them to the Buckaroo client for confirmation.
     * If the credentials are valid, a success response is generated.
     * Otherwise, an error message is returned stating the credentials are invalid.
     *
     * @param string $merchantKey The merchant key to validate.
     * @param string $secretKey The secret key to validate.
     * @return Json The JSON response indicating whether the credentials are valid.
     * @throws \Exception
     */
    private function validateCredentials(string $merchantKey, string $secretKey): Json
    {
        if ($this->client->confirmCredential($merchantKey, $secretKey)) {
            return $this->doResponse([
                'success' => true
            ]);
        } else {
            return $this->doResponse([
                'success' => false,
                'error_message' => 'The credentials are not valid!'
            ]);
        }
    }

    /**
     * Set Response on resultJson
     *
     * @param array $response
     * @return Json
     */
    private function doResponse(array $response): Json
    {
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($response);
    }
}
