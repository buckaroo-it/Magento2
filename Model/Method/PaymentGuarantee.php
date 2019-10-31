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
namespace TIG\Buckaroo\Model\Method;

use Magento\Sales\Api\Data\OrderAddressInterface;
use TIG\Buckaroo\Model\Invoice;

class PaymentGuarantee extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_paymentguarantee';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'paymentguarantee';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isGateway               = true;

    /**
     * @var bool
     */
    protected $_canOrder                = true;

    /**
     * @var bool
     */
    protected $_canAuthorize            = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial       = true;

    /**
     * @var bool
     */
    protected $_canRefund               = true;

    /**
     * @var bool
     */
    protected $_canVoid                 = false;

    /**
     * @var bool
     */
    protected $_canUseInternal          = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout          = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction   = false;

    /**
     * @var bool
     */
    protected $_isPartialCapture        = false;

    /**
     * @var \TIG\Buckaroo\Model\InvoiceFactory
     */
    private $invoiceFactory;

    /**
     * @var \TIG\Buckaroo\Api\InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \TIG\Buckaroo\Service\Formatter\AddressFormatter
     */
    private $addressFormatter;
    // @codingStandardsIgnoreEnd

    /**
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory       $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory            $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                            $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface      $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                    $logger
     * @param \Magento\Developer\Helper\Data                          $developmentHelper
     * @param \TIG\Buckaroo\Model\InvoiceFactory                      $invoiceFactory
     * @param \TIG\Buckaroo\Api\InvoiceRepositoryInterface            $invoiceRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder            $searchCriteriaBuilder
     * @param \TIG\Buckaroo\Service\Formatter\AddressFormatter        $addressFormatter
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param \TIG\Buckaroo\Gateway\GatewayInterface                  $gateway
     * @param \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory    $transactionBuilderFactory
     * @param \TIG\Buckaroo\Model\ValidatorFactory                    $validatorFactory
     * @param \TIG\Buckaroo\Helper\Data                               $helper
     * @param \Magento\Framework\App\RequestInterface                 $request
     * @param \TIG\Buckaroo\Model\RefundFieldsFactory                 $refundFieldsFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory              $configProviderFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Factory       $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data                  $priceHelper
     * @param array                                                   $data
     */
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
        \TIG\Buckaroo\Model\InvoiceFactory $invoiceFactory,
        \TIG\Buckaroo\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \TIG\Buckaroo\Service\Formatter\AddressFormatter $addressFormatter,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \TIG\Buckaroo\Gateway\GatewayInterface $gateway = null,
        \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \TIG\Buckaroo\Model\ValidatorFactory $validatorFactory = null,
        \TIG\Buckaroo\Helper\Data $helper = null,
        \Magento\Framework\App\RequestInterface $request = null,
        \TIG\Buckaroo\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory = null,
        \TIG\Buckaroo\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
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

        $this->invoiceFactory = $invoiceFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->addressFormatter = $addressFormatter;
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (!isset($data['additional_data']['termsCondition'])) {
            return $this;
        }

        $additionalData = $data['additional_data'];
        $this->getInfoInstance()->setAdditionalInformation('termsCondition', $additionalData['termsCondition']);
        $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
        $this->getInfoInstance()->setAdditionalInformation(
            'customer_billingName',
            $additionalData['customer_billingName']
        );
        $this->getInfoInstance()->setAdditionalInformation('customer_iban', $additionalData['customer_iban']);

        $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
        $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
        $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);

        return $this;
    }

    /**
     * Check capture availability
     *
     * @return bool
     * @api
     */
    public function canCapture()
    {
        if ($this->getConfigData('payment_action') == 'order') {
            return false;
        }
        return $this->_canCapture;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'paymentguarantee',
            'Action'           => 'PaymentInvitation',
            'Version'          => 1,
            'RequestParameter' => $this->getPaymentGuaranteeRequestParameters($payment),
        ];

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setReturnUrl('');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation(
            'skip_push', 1
        );

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'paymentguarantee',
            'Action'           => 'PartialInvoice',
            'Version'          => 1,
            'RequestParameter' => $this->keepKeysFromParameters(
                [
                    'AmountVat',
                    'InvoiceDate',
                    'DateDue',
                    'PaymentMethodsAllowed',
                    'SendMail',
                    'CustomerCode'
                ],
                $this->getPaymentGuaranteeRequestParameters($payment)
            )
        ];

        /** @var \Magento\Sales\Model\Order $order */
        $order       = $payment->getOrder();
        $totalAmount = $this->calculateInvoiceAmount($order);

        $transactionBuilder->setOrder($order)
            ->setServices($services)
            ->setAmount($totalAmount)
            ->setMethod('TransactionRequest')
            ->setReturnUrl('')
            ->setInvoiceId($this->getPartialId($payment))
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(
                    self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
                )
            );

        if ($this->_isPartialCapture) {
            /** @noinspection PhpUndefinedMethodInspection */
            $transactionBuilder->setOriginalTransactionKey(
                $payment->getParentTransactionId()
            );
        }

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'paymentguarantee',
            'Action'           => 'Order',
            'Version'          => 1,
            'RequestParameter' => $this->stripKeysFromParameters(
                [
                    'InvoiceDate',
                    'DateDue',
                    'PaymentMethodsAllowed'
                ],
                $this->getPaymentGuaranteeRequestParameters($payment)
            )
        ];

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setReturnUrl('');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation(
            'skip_push', 1
        );

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'    => 'paymentguarantee',
            'Action'  => 'CreditNote',
            'Version' => 1,
            'RequestParameter' => $this->keepKeysFromParameters(
                [
                    'AmountVat',
                    'OriginalInvoiceNumber'
                ],
                $this->getPaymentGuaranteeRequestParameters($payment)
            )
        ];

        $requestParams = $this->addExtraFields($this->_code);
        $services = array_merge($services, $requestParams);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($order)
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setReturnUrl('')
            ->setInvoiceId($this->getPartialId($payment))
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            )
            ->setChannel('CallCenter');

        if ($this->isPartialRefund($payment)) {
            $transactionBuilder->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function afterCapture($payment, $response)
    {
        $responseInvoiceId = $response[0]->Invoice;
        $responseTransactionId = $response[0]->Key;

        $buckarooInvoice = $this->invoiceFactory->create();
        $buckarooInvoice->setInvoiceTransactionId($responseTransactionId);
        $buckarooInvoice->setInvoiceNumber($responseInvoiceId);
        $this->invoiceRepository->save($buckarooInvoice);

        return parent::afterCapture($payment, $response);
    }

    /**
     * @param array $parameters
     * @param array $keys
     *
     * @return array
     */
    private function stripKeysFromParameters($keys, $parameters)
    {
        $stripped = array_filter($parameters, function ($value) use ($keys) {
             return !in_array($value['Name'], $keys);
        });

        return array_values($stripped);
    }

    /**
     * @param $keys
     * @param $parameters
     *
     * @return array
     */
    private function keepKeysFromParameters($keys, $parameters)
    {
        $stripped = array_filter($parameters, function ($value) use ($keys) {
            return in_array($value['Name'], $keys);
        });

        return array_values($stripped);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function getPaymentGuaranteeRequestParameters($payment)
    {
        /** @var \TIG\Buckaroo\Model\ConfigProvider\Method\PaymentGuarantee $config */
        $config = $this->configProviderMethodFactory->get('paymentguarantee');

        /** @var OrderAddressInterface $billingAddress */
        $billingAddress = $payment->getOrder()->getBillingAddress();
        /** @var OrderAddressInterface $shippingAddress */
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        $customerId = $billingAddress->getCustomerId()
            ? $billingAddress->getCustomerId()
            : $payment->getOrder()->getIncrementId();

        $telephone = $this->addressFormatter->formatTelephone(
            $billingAddress->getTelephone(),
            $billingAddress->getCountryId()
        );

        $defaultValues = [
            [
                '_'    => date('Y-m-d'),
                'Name' => 'InvoiceDate'
            ],
            [
                '_'    => date('Y-m-d',strtotime('+14 day', time())),
                'Name' => 'DateDue'
            ],
            [
                '_'    => $customerId,
                'Name' => 'CustomerCode'
            ],
            [
                '_'    => strtoupper(substr($billingAddress->getFirstname(), 0, 1)),
                'Name' => 'CustomerInitials'
            ],
            [
                '_'    => $billingAddress->getFirstname(),
                'Name' => 'CustomerFirstName'
            ],
            [
                '_'    => $billingAddress->getLastname(),
                'Name' => 'CustomerLastName'
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'CustomerGender'
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_DoB'),
                'Name' => 'CustomerBirthDate'
            ],
            [
                '_'    => $billingAddress->getEmail(),
                'Name' => 'CustomerEmail'
            ],
            [
                '_'    => $telephone['clean'],
                'Name' => ($telephone['mobile'] ? 'MobilePhoneNumber' : 'PhoneNumber')
            ],
            [
                '_'    => $config->getPaymentMethodToUse(),
                'Name' => 'PaymentMethodsAllowed'
            ],
            [
                '_'    => $config->getSendMail(),
                'Name' => 'SendMail'
            ]
        ];

        $taxAmount = $this->calculateTaxAmount($payment);
        $defaultValues = array_merge($defaultValues, $taxAmount);

        if ($payment->getAdditionalInformation('customer_iban')) {
            $defaultValues = array_merge($defaultValues, [
                [
                    '_'    => $payment->getAdditionalInformation('customer_iban'),
                    'Name' => 'CustomerIBAN'
                ]
            ]);
        }

        $invoiceId = $this->getInvoiceIdFromTransaction($payment);

        if (count($invoiceId)) {
            $defaultValues = array_merge($defaultValues, $invoiceId);
        }

        if ($this->isAddressDataDifferent($billingAddress, $shippingAddress)) {
            $returnValues = array_merge($defaultValues, $this->singleAddress($billingAddress, 'INVOICE'));
            return array_merge($returnValues, $this->singleAddress($shippingAddress, 'SHIPPING', 2));
        }

        return array_merge($defaultValues, $this->singleAddress($billingAddress, 'INVOICE,SHIPPING'));
    }

    /**
     * @param OrderAddressInterface $address
     * @param string $addressType
     * @param int $id
     *
     * @return array
     */
    private function singleAddress($address, $addressType, $id = 1)
    {
        $street = $this->addressFormatter->formatStreet($address->getStreet());

        return [
            [
                '_'       => $addressType,
                'Name'    => 'AddressType',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ],
            [
                '_'       => $street['street'],
                'Name'    => 'Street',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ],
            [
                '_'       => $street['house_number'],
                'Name'    => 'HouseNumber',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ],
            [
                '_'       => $address->getPostcode(),
                'Name'    => 'ZipCode',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ],
            [
                '_'       => $address->getCity(),
                'Name'    => 'City',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ],
            [
                '_'       => $address->getCountryId(),
                'Name'    => 'Country',
                'Group'   => 'address',
                'GroupID' => 'address_'.$id
            ]
        ];
    }

    /**
     * @param array|OrderAddressInterface $addressOne
     * @param array|OrderAddressInterface $addressTwo
     *
     * @return bool
     */
    private function isAddressDataDifferent($addressOne, $addressTwo)
    {
        if ($addressOne === null || $addressTwo === null) {
            return false;
        }

        if ($addressOne instanceof OrderAddressInterface) {
            $addressOne = $addressOne->getData();
        }

        if ($addressTwo instanceof OrderAddressInterface) {
            $addressTwo = $addressTwo->getData();
        }

        $arrayDifferences = $this->calculateAddressDataDifference($addressOne, $addressTwo);

        return !empty($arrayDifferences);
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return array
     */
    private function calculateAddressDataDifference($addressOne, $addressTwo)
    {
        $keysToExclude = array_flip([
            'prefix',
            'telephone',
            'fax',
            'created_at',
            'email',
            'customer_address_id',
            'vat_request_success',
            'vat_request_date',
            'vat_request_id',
            'vat_is_valid',
            'vat_id',
            'address_type',
            'extension_attributes',
        ]);

        $arrayDifferences = array_diff(
            array_diff_key($addressOne, $keysToExclude),
            array_diff_key($addressTwo, $keysToExclude)
        );

        return $arrayDifferences;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return int|float
     */
    private function calculateInvoiceAmount($order)
    {
        $invoiceAmount = 0;

        $numberOfInvoices = $order->getInvoiceCollection()->count();

        if (!$numberOfInvoices) {
            return $invoiceAmount;
        }

        $i = 0;
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (++$i !== $numberOfInvoices) {
                continue;
            }
            $invoiceAmount = $invoice->getBaseGrandTotal();
        }

        $this->setCaptureType($order, $invoiceAmount);
        return $invoiceAmount;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function calculateTaxAmount($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $taxAmount = $order->getBaseTaxAmount();

        $numberOfInvoices = $order->getInvoiceCollection()->count();

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        // if there's an invoice but no creditmemo, it means a capture is in progress.
        if ($numberOfInvoices && !$creditmemo) {
            $i = 0;
            /** @var \Magento\Sales\Model\Order\Invoice $invoice */
            foreach ($order->getInvoiceCollection() as $invoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $taxAmount = $invoice->getBaseTaxAmount();
            }
        }

        //If there's a creditmemo in the payment, it means a refund is currently in progress.
        if ($creditmemo) {
            $taxAmount = $creditmemo->getBaseTaxAmount();
        }

        $taxAmountParameter = [
            [
                '_'    => $taxAmount,
                'Name' => 'AmountVat'
            ]
        ];

        return $taxAmountParameter;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    private function getInvoiceIdFromTransaction($payment)
    {
        $originalInvoiceNumber = [];

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        if (!$creditmemo) {
            return $originalInvoiceNumber;
        }

        $transactionId = $creditmemo->getInvoice()->getTransactionId();
        $buckarooInvoiceNumber = $payment->getOrder()->getIncrementId();

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('invoice_transaction_id', $transactionId);
        $searchCriteria->setPageSize(1);
        $list = $this->invoiceRepository->getList($searchCriteria->create());

        if ($list->getTotalCount()) {
            /** @var Invoice $buckarooInvoice */
            $buckarooInvoice = $list->getItems()[0];
            $buckarooInvoiceNumber = $buckarooInvoice->getInvoiceNumber();
        }

        if (!$buckarooInvoiceNumber) {
            return $originalInvoiceNumber;
        }

        $originalInvoiceNumber = [
            [
                '_'    => $buckarooInvoiceNumber,
                'Name' => 'OriginalInvoiceNumber'
            ]
        ];

        return $originalInvoiceNumber;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return string
     */
    private function getPartialId($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $numberOfCreditmemos = $order->getCreditmemosCollection()->count();

        $incrementNumber = $numberOfInvoices + $numberOfCreditmemos;

        if (null !== $payment->getCreditmemo()) {
            $incrementNumber += 1;
        }

        $partialId = $order->getIncrementId() . '-' . $incrementNumber;

        return $partialId;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $invoiceAmount
     */
    private function setCaptureType($order, $invoiceAmount)
    {
        $numberOfInvoices = $order->getInvoiceCollection()->count();

        $this->_isPartialCapture = !($order->getBaseGrandTotal() == $invoiceAmount && $numberOfInvoices == 1);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool
     */
    private function isPartialRefund($payment)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        $invoice = $creditmemo->getInvoice();

        return ($invoice->getBaseGrandTotal() != $creditmemo->getBaseGrandTotal());
    }
}
