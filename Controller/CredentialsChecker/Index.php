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

namespace Buckaroo\Magento2\Controller\CredentialsChecker;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Encryption\Encryptor;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Checkout\Model\ConfigProviderInterface
     */
    protected $accountConfig;

    private $urlBuilder;
    private $formKey;
    private $helper;
    private $encryptor;
    private $configProviderAccount;
    private $transactionBuilderFactory;
    private $gateway;
    private $validatorFactory;
    private $client;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param Log                                                 $logger
     * @param \Magento\Sales\Model\Order                          $order
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Log $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Buckaroo\Magento2\Helper\Data $helper,
        Encryptor $encryptor,
        Account $configProviderAccount,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory,
        \Buckaroo\Magento2\Gateway\Http\Client\Json $client
    ) {
        parent::__construct($context);
        $this->logger             = $logger;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->accountConfig      = $configProviderFactory->get('account');
        $this->urlBuilder         = $urlBuilder;
        $this->formKey            = $formKey;
        $this->helper             = $helper;
        $this->encryptor          = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->transactionBuilderFactory = $transactionBuilderFactory;
        $this->gateway            = $gateway;
        $this->validatorFactory   = $validatorFactory;
        $this->client = $client;
    }

    public function execute()
    {
        if ($params = $this->getRequest()->getParams()) {
            if (!empty($params['secretKey']) && !empty($params['merchantKey'])) {

                if (preg_match('/[^\*]/', $params['secretKey'])) {
                    $secretKey =  $params['secretKey'];
                } else {
                    $secretKey =  $this->encryptor->decrypt($this->configProviderAccount->getSecretKey());
                }

                if (preg_match('/[^\*]/', $params['merchantKey'])) {
                    $merchantKey =  $params['merchantKey'];
                } else {
                    $merchantKey =  $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey());
                }

                $mode = $params['mode'] ?? \Buckaroo\Magento2\Helper\Data::MODE_TEST;

                if (!$this->testXml($mode, $merchantKey, $message)) {
                    return $this->doResponse([
                        'success' => false,
                        'error_message' => $message
                    ]);
                }

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
        }

        return $this->doResponse([
            'success' => false,
            'error_message' => __('Failed to start validation process due to lack of data')
        ]);
    }

    private function testXml($mode, $merchantKey, &$message)
    {

        $services = [
            'Name'             => 'Idin',
            'Action'           => 'verify',
            'Version'          => 0,
            'RequestParameter' => [
                [
                    '_'    => 'BANKNL2Y',
                    'Name' => 'issuerId',
                ],
            ],
        ];

        $transactionBuilder = $this->transactionBuilderFactory->get('datarequest');
        $transactionBuilder->setMerchantKey($merchantKey);
        $transaction        = $transactionBuilder
            ->setServices($services)
            ->setMethod('DataRequest')
            ->setReturnUrl('')
            ->build();

        try {
            $response = $this->gateway->setMode($mode)->authorize($transaction);
        } catch (\Exception $e) {
            $message = __('It seems like "Merchant key" and/or "Certificate file" are incorrect');
            return false;
        }

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            $message = __('It seems like "Certificate file" is incorrect');
            return false;
        }

        return true;
    }

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

        if ($this->client->getStatus() ==  200) {
            return true;
        } else {
            $message = __('It seems like "Merchant key" and/or "Secret key" are incorrect');
            return false;
        }
    }

    private function doResponse($response)
    {
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
