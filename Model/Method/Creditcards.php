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

use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;

class Creditcards extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_creditcards';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'creditcards';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /** @var \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters */
    private $serviceParameters;

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
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway = null,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory = null,
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
            $eventManager,
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
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        /**
         * If there are no giftcards chosen, we can't be available
         */
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards $ccConfig
         */
        $gcConfig = $this->configProviderMethodFactory->get('creditcards');

        if ($gcConfig->getHostedFieldsClientId() === null || $gcConfig->getHostedFieldsClientSecret() === null) {
            return false;
        }
        /**
         * Return the regular isAvailable result
         */
        return parent::isAvailable($quote);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['customer_encrypteddata'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_encrypteddata',
                $data['additional_data']['customer_encrypteddata']
            );
        }

        if (isset($data['additional_data']['customer_creditcardcompany'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_creditcardcompany',
                $data['additional_data']['customer_creditcardcompany']
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

        $serviceAction = $this->getPayRemainder($payment, $transactionBuilder, 'PayWithToken', 'PayRemainderEncrypted');

        $services = [];
        $services[] = $this->getCreditcardsService($payment, $serviceAction);

        $filterParameter = [
            ['Name' => 'AllowedServices'],
            ['Name' => 'Gender', 'Group' => 'Person']
        ];

        $cmService = $this->serviceParameters->getCreateCombinedInvoice($payment, 'creditcards', $filterParameter);
        if (count($cmService) > 0) {
            $services[] = $cmService;

            $payment->setAdditionalInformation('skip_push', 2);
        }

        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards $creditcardsConfig */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getCreditcardsService($payment, $serviceAction)
    {
        $additionalInformation = $payment->getAdditionalInformation();

        if (!isset($additionalInformation['customer_encrypteddata'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the encrypted creditcard data to Buckaroo.'));
        }

        if (!isset($additionalInformation['customer_creditcardcompany'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the creditcard company data to Buckaroo.'));
        }

        $services = [
            'Name'             => $additionalInformation['customer_creditcardcompany'],
            'Action'           => $serviceAction,
            'Version'          => 0,
            'RequestParameter' => [
                [
                    '_'    => $additionalInformation['customer_encrypteddata'],
                    'Name' => 'SessionId',
                ],
            ],
        ];

        return $services;
    }

    /**
     * {@inheritdoc}
     */
    public function canProcessPostData($payment, $postData)
    {
        $orderState = $payment->getOrder()->getState();
        if ($orderState == \Magento\Sales\Model\Order::STATE_PROCESSING && $postData['brq_statuscode'] == "792") {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function afterOrder($payment, $response)
    {
        if (empty($response[0]->Services->Service)) {
            return parent::afterOrder($payment, $response);
        }

        $invoiceKey = '';
        $services = $response[0]->Services->Service;

        if (!is_array($services)) {
            $services = [$services];
        }

        foreach ($services as $service) {
            if ($service->Name == 'CreditManagement3' && $service->ResponseParameter->Name == 'InvoiceKey') {
                $invoiceKey = $service->ResponseParameter->_;
            }
        }

        if (strlen($invoiceKey) > 0) {
            $payment->setAdditionalInformation('buckaroo_cm3_invoice_key', $invoiceKey);
        }

        return parent::afterOrder($payment, $response);
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
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        return $additionalInformation['customer_creditcardcompany'];
    }
}
