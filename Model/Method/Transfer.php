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

namespace Buckaroo\Magento2\Model\Method;

use Magento\Sales\Model\Order;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Registry\BuckarooRegistry as BuckarooRegistry;

class Transfer extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_transfer';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'transfer';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    // @codingStandardsIgnoreEnd

    /**
     * @var bool
     */
    public $usesRedirect                = false;

    /** @var \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters */
    private $serviceParameters;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        BuckarooRegistry $buckarooRegistry,
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
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway = null,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory = null,
        \Buckaroo\Magento2\Helper\Data $helper = null,
        \Buckaroo\Magento2\Helper\PaymentGroupTransaction $paymentGroupTransactionHelper,
        \Magento\Framework\App\RequestInterface $request = null,
        \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
        \Magento\Framework\Pricing\Helper\Data $priceHelper = null,
        \Magento\Framework\HTTP\Client\Curl $curl,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $buckarooRegistry,
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
            $resource,
            $resourceCollection,
            $gateway,
            $transactionBuilderFactory,
            $validatorFactory,
            $helper,
            $paymentGroupTransactionHelper,
            $request,
            $refundFieldsFactory,
            $configProviderFactory,
            $configProviderMethodFactory,
            $priceHelper,
            $curl,
            $data
        );

        $this->serviceParameters = $serviceParameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [];
        $services[] = $this->getTransferService($payment);

        $filterParameter = [
            ['Name' => 'AllowedServices'],
            ['Name' => 'Gender', 'Group' => 'Person']
        ];

        $cmService = $this->serviceParameters->getCreateCombinedInvoice($payment, 'transfer', $filterParameter);
        if (count($cmService) > 0) {
            $services[] = $cmService;

            $payment->setAdditionalInformation(
                'skip_push', 2
            );
        }

        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer $transferConfig */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getTransferService($payment)
    {
        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer $transferConfig */
        $transferConfig = $this->configProviderMethodFactory->get('transfer');

        $dueDays = abs($transferConfig->getDueDate());

        $now = new \DateTime();
        $now->modify('+' . $dueDays . ' day');

        /**@var \Magento\Sales\Model\Order\Address $billingAddress */
        $billingAddress = $payment->getOrder()->getBillingAddress();

        $services = [
            'Name'             => 'transfer',
            'Action'           => 'Pay',
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $billingAddress->getFirstname(),
                    'Name' => 'CustomerFirstName',
                ],
                [
                    '_'    => $billingAddress->getLastname(),
                    'Name' => 'CustomerLastName',
                ],
                [
                    '_'    => $billingAddress->getCountryId(),
                    'Name' => 'CustomerCountry',
                ],
                [
                    '_'    => $payment->getOrder()->getCustomerEmail(),
                    'Name' => 'CustomerEmail',
                ],
                [
                    '_'    => $now->format('Y-m-d'),
                    'Name' => 'DateDue'
                ],
                [
                    '_'    => $transferConfig->getSendEmail(),
                    'Name' => 'SendMail'
                ]
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

    protected function getRefundTransactionBuilderChannel()
    {
        return '';
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
        return 'transfer';
    }
}
