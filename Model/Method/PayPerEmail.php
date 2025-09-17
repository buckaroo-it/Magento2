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

use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;

class PayPerEmail extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_payperemail';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'payperemail';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = false;

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
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);
        $this->assignDataCommonV2($data);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $services = [];
        $services[] = $this->getPayperemailService($payment);

        $cmService = $this->serviceParameters->getCreateCombinedInvoice($payment, $this->buckarooPaymentMethodCode);
        if (count($cmService) > 0) {
            $services[] = $cmService;
        }

        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setAdditionalParameter('fromPayPerEmail', 1)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $areaCode = $this->_appState->getAreaCode();

        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail $ppeConfig */
        $ppeConfig = $this->configProviderMethodFactory->get('payperemail');

        if (!$ppeConfig->isVisibleForAreaCode($areaCode)) {
            return false;
        }

        /**
         * Check if payment group transaction is already paid
         */
        $orderId = $quote ? $quote->getReservedOrderId() : null;
        if ($orderId) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $paymentGroupTransaction = $objectManager->get(\Buckaroo\Magento2\Helper\PaymentGroupTransaction::class);
            
            if ($paymentGroupTransaction->getAlreadyPaid($orderId) > 0) {
                return false;
            }
        }

        /**
         * Return the regular isAvailable result
         */
        return parent::isAvailable($quote);
    }

    /**
     * {@inheritdoc}
     */
    public function canProcessPostData($payment, $postData)
    {
        // $transactionKey = $payment->getAdditionalInformation(AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
        // if ($transactionKey != $postData['brq_transactions']) {
        //     return false;
        // }

        $orderState = $payment->getOrder()->getState();
        if ($orderState == \Magento\Sales\Model\Order::STATE_PROCESSING && $postData['brq_statuscode'] == "792") {
            return false;
        }

        return true;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function getPayperemailService($payment)
    {
        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail $config */
        $config = $this->configProviderMethodFactory->get('payperemail');
        $storeId = $payment->getOrder()->getStoreId();

        $params = [
            [
                '_'    => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'customergender',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_email'),
                'Name' => 'CustomerEmail',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_billingFirstName'),
                'Name' => 'CustomerFirstName',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_billingMiddleName') . ' ' . $payment->getAdditionalInformation('customer_billingLastName'),
                'Name' => 'CustomerLastName',
            ],
            [
                '_'    => $config->getSendMail() ? 'false' : 'true',
                'Name' => 'MerchantSendsEmail',
            ],
            [
                '_'    => $this->getPaymentMethodsAllowed($config, $storeId),
                'Name' => 'PaymentMethodsAllowed',
            ],
        ];

        if ($config->getExpireDays()) {
            $params[] = [
                '_' => date('Y-m-d', time() + $config->getExpireDays() * 86400),
                'Name' => 'ExpirationDate',
            ];
        }

        $services = [
            'Name'             => 'payperemail',
            'Action'           => 'PaymentInvitation',
            'Version'          => 1,
            'RequestParameter' => $params
        ];

        return $services;
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

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
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

    private function getPaymentMethodsAllowed($config, $storeId)
    {
        if ($methods = $config->getPaymentMethod($storeId)) {
            $methods = explode(',', (string)$methods);
            $activeCards = '';
            foreach ($methods as $key => $value) {
                if ($value === 'giftcard') {
                    $giftcardsConfig = $this->configProviderMethodFactory->get('giftcards');
                    if ($activeCards = $giftcardsConfig->getAllowedCards($storeId)) {
                        unset($methods[$key]);
                    }
                }
            }
            if ($activeCards) {
                $methods = array_merge($methods, explode(',', (string)$activeCards));
            }
            $methods = join(',', $methods);
        }
        return $methods;
    }


}
