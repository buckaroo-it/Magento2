<?php
// @codingStandardsIgnoreFile
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

namespace Buckaroo\Magento2\Model\Method;

use Magento\Sales\Model\Order\Payment;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
class SepaDirectDebit extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_sepadirectdebit';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'sepadirectdebit';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    // @codingStandardsIgnoreEnd

    /** @var \Magento\Framework\Message\ManagerInterface */
    public $messageManager;

    /** @var \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters */
    private $serviceParameters;

    /**
     * @var bool
     */
    public $usesRedirect                = false;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Developer\Helper\Data $developmentHelper,
        \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters $serviceParameters,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        Config $taxConfig,
        Calculation $taxCalculation,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        BuckarooLog $buckarooLog,
        SoftwareData $softwareData,
        AddressFactory $addressFactory,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway = null,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory = null,
        \Magento\Framework\Message\ManagerInterface $messageManager = null,
        \Buckaroo\Magento2\Helper\Data $helper = null,
        \Magento\Framework\App\RequestInterface $request = null,
        \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
        \Magento\Framework\Pricing\Helper\Data $priceHelper = null,
        array $data = []
    ) {
        parent::__construct(
            $objectManager,
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $developmentHelper,
            $quoteFactory,
            $taxConfig,
            $taxCalculation,
            $configProviderBuckarooFee,
            $buckarooLog,
            $softwareData,
            $addressFactory,
            $secondChanceRepository,
            $resource,
            $resourceCollection,
            $gateway,
            $transactionBuilderFactory,
            $validatorFactory,
            $helper,
            $request,
            $refundFieldsFactory,
            $configProviderFactory,
            $configProviderMethodFactory,
            $priceHelper,
            $data
        );

        $this->serviceParameters = $serviceParameters;
        $this->messageManager = $messageManager;
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['buckaroo_skip_validation'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'buckaroo_skip_validation',
                $data['additional_data']['buckaroo_skip_validation']
            );
        }

        if (isset($data['additional_data']['customer_bic'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_bic',
                $data['additional_data']['customer_bic']
            );
        }

        if (isset($data['additional_data']['customer_iban'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_iban',
                $data['additional_data']['customer_iban']
            );
        }

        if (isset($data['additional_data']['customer_account_name'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_account_name',
                $data['additional_data']['customer_account_name']
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [];
        $services[] = $this->getSepaService();

        $filterParameter = [
            ['Name' => 'AllowedServices'],
            ['Name' => 'Gender', 'Group' => 'Person']
        ];


        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation('skip_push', 1);

        $cmService = $this->serviceParameters->getCreateCombinedInvoice($payment, 'sepadirectdebit', $filterParameter);
        if (count($cmService) > 0) {
            $services[] = $cmService;
            $payment->setAdditionalInformation('skip_push', 2);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    private function getSepaService()
    {
        $services = [
            'Name'             => 'sepadirectdebit',
            'Action'           => 'Pay',
            'Version'          => 1,
            'RequestParameter' => [
                [
                    '_'    => $this->getInfoInstance()->getAdditionalInformation('customer_account_name'),
                    'Name' => 'customeraccountname',
                ],
                [
                    '_'    => $this->getInfoInstance()->getAdditionalInformation('customer_iban'),
                    'Name' => 'CustomerIBAN',
                ],
            ],
        ];

        if ($this->getInfoInstance()->getAdditionalInformation('customer_bic')) {
            $services['RequestParameter'][] = [
                '_'    => $this->getInfoInstance()->getAdditionalInformation('customer_bic'),
                'Name' => 'CustomerBIC',
            ];
        }

        return $services;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        return false;
    }

    protected function afterOrder($payment, $response)
    {
        return $this->afterOrderCommon($payment, $response);
    }

    /**
     * @param $responseParameter
     *
     * @return string
     */
    protected function getCM3InvoiceKey($responseParameter)
    {
        $invoiceKey = '';

        if (!is_array($responseParameter)) {
            return $this->parseCM3ResponeParameter($responseParameter, $invoiceKey);
        }

        foreach ($responseParameter as $parameter) {
            $invoiceKey = $this->parseCM3ResponeParameter($parameter, $invoiceKey);
        }

        return $invoiceKey;
    }

    /**
     * @param $responseParameter
     * @param $invoiceKey
     *
     * @return mixed
     */
    protected function parseCM3ResponeParameter($responseParameter, $invoiceKey)
    {
        if ($responseParameter->Name == 'InvoiceKey') {
            $invoiceKey = $responseParameter->_;
        }

        return $invoiceKey;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface|\Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @param array|\StdCLass                                                                    $response
     *
     * @return $this
     */
    protected function afterAuthorize($payment, $response)
    {
        if (!empty($response[0]->ConsumerMessage) && $response[0]->ConsumerMessage->MustRead == 1) {
            $consumerMessage = $response[0]->ConsumerMessage;

            $this->messageManager->addSuccessMessage(
                __($consumerMessage->Title)
            );
            $this->messageManager->addSuccessMessage(
                __($consumerMessage->PlainText)
            );
        }

        return parent::afterAuthorize($payment, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        $services = $this->serviceParameters->getCreateCreditNote($payment);

        if (count($services) <= 0) {
            return true;
        }

        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $transactionBuilder->setOrder($payment->getOrder())
            ->setAmount(0)
            ->setType('void')
            ->setServices($services)
            ->setMethod('DataRequest')
            ->setInvoiceId($payment->getOrder()->getIncrementId() . '-creditnote')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(
                    self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
                )
            );

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this;
        }

        $customerBic = $paymentInfo->getAdditionalInformation('customer_bic');
        $customerIban = $paymentInfo->getAdditionalInformation('customer_iban');
        $customerAccountName = $paymentInfo->getAdditionalInformation('customer_account_name');

        if (empty($customerAccountName) || str_word_count($customerAccountName) < 2) {
            throw new \Buckaroo\Magento2\Exception(__('Please enter a valid bank account holder name'));
        }
        if ($paymentInfo instanceof Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }

        $ibanValidator = $this->objectManager->create(\Zend\Validator\Iban::class);
        if (empty($customerIban) || !$ibanValidator->isValid($customerIban)) {
            throw new \Buckaroo\Magento2\Exception(__('Please enter a valid bank account number'));
        }

        if ($billingCountry != 'NL' && !preg_match(self::BIC_NUMBER_REGEX, $customerBic)) {
            throw new \Buckaroo\Magento2\Exception(__('Please enter a valid BIC number'));
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $this->buckarooPaymentMethodCode;
    }
}
