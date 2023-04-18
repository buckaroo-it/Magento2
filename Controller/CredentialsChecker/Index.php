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
use Buckaroo\Magento2\Gateway\Http\Client\Json as HttpClientJson;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Phrase;

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
     * @var HttpClientJson
     */
    private $client;

    /**
     * Check Credentials in Admin
     *
     * @param Context $context
     * @param Factory $configProviderFactory
     * @param Encryptor $encryptor
     * @param Account $configProviderAccount
     * @param HttpClientJson $client
     * @throws BuckarooException
     */
    public function __construct(
        Context $context,
        Factory $configProviderFactory,
        Encryptor $encryptor,
        Account $configProviderAccount,
        HttpClientJson $client
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
    public function execute()
    {
        if ($params = $this->getRequest()->getParams()
            && (!empty($params['secretKey']) && !empty($params['merchantKey']))) {

            if (preg_match('/[^\*]/', $params['secretKey'])) {
                $secretKey = $params['secretKey'];
            } else {
                $secretKey = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey());
            }

            if (preg_match('/[^\*]/', $params['merchantKey'])) {
                $merchantKey = $params['merchantKey'];
            } else {
                $merchantKey = $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey());
            }

            $mode = $params['mode'] ?? Data::MODE_TEST;

            if (!$this->testJson($mode, $merchantKey, $secretKey, $message)) {
                return $this->doResponse([
                    'success' => false,
                    'error_message' => $message
                ]);
            }

            return $this->doResponse([
                'success' => true
            ]);

        }

        return $this->doResponse([
            'success' => false,
            'error_message' => __('Failed to start validation process due to lack of data')
        ]);
    }

    /**
     * Create test request
     *
     * @param int|string $mode
     * @param string $merchantKey
     * @param string $secretKey
     * @param Phrase|string $message
     * @return bool
     */
    private function testJson($mode, $merchantKey, $secretKey, &$message)
    {
        $this->client->setSecretKey($secretKey);
        $this->client->setWebsiteKey($merchantKey);

        $data = [
            "Services" => [
                "ServiceList" => [
                    [
                        "Action" => "Pay",
                        "Name" => "ideal",
                        "Parameters" => [
                            [
                                "Name" => 'issuer',
                                "Value" => 'ABNANL2A'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->client->doRequest($data, $mode);

        if ($this->client->getStatus() == 200) {
            return true;
        } else {
            $message = __('It seems like "Merchant key" and/or "Secret key" are incorrect');
            return false;
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
