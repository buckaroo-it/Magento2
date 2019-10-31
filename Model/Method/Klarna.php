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

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use TIG\Buckaroo\Service\Formatter\AddressFormatter;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Checkout\Model\Cart;
use Zend_Locale;

class Klarna extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_klarna';

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    /** Klarna Article Types */
    const KLARNA_ARTICLE_TYPE_GENERAL = 'General';
    const KLARNA_ARTICLE_TYPE_HANDLINGFEE = 'HandlingFee';
    const KLARNA_ARTICLE_TYPE_SHIPMENTFEE = 'ShipmentFee';


    /**
     * Business methods that will be used in klarna.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'klarna';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction   = false;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    // @codingStandardsIgnoreEnd

    /** @var SoftwareData */
    private $softwareData;

    /** @var Calculation */
    private $taxCalculation;

    /** @var Config */
    private $taxConfig;

    /** @var Cart */
    private $cart;

    /** @var AddressFormatter */
    private $addressFormatter;

    /** @var int */
    private $groupId = 1;

    private $context;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Developer\Helper\Data $developmentHelper
     * @param SoftwareData $softwareData
     * @param Config $taxConfig
     * @param Calculation $taxCalculation
     * @param Cart $cart
     * @param AddressFormatter $addressFormatter
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \TIG\Buckaroo\Gateway\GatewayInterface $gateway
     * @param \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory
     * @param \TIG\Buckaroo\Model\ValidatorFactory $validatorFactory
     * @param \TIG\Buckaroo\Helper\Data $helper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \TIG\Buckaroo\Model\RefundFieldsFactory $refundFieldsFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param array $data
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
        SoftwareData $softwareData,
        Config $taxConfig,
        Calculation $taxCalculation,
        Cart $cart,
        AddressFormatter $addressFormatter,
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

        $this->context = $context;
        $this->softwareData = $softwareData;
        $this->taxConfig = $taxConfig;
        $this->taxCalculation = $taxCalculation;
        $this->cart = $cart;
        $this->addressFormatter = $addressFormatter;
    }

    /**
     * {@inheritDoc}
     */
    public function canCapture()
    {
        if ($this->getConfigData('payment_action') == 'order') {
            return false;
        }
        return $this->_canCapture;
    }

    public function canCapturePartial()
    {
        if ($this->getInfoInstance()->getOrder()->getDiscountAmount() < 0) {
            return false;
        }
        return $this->_canCapturePartial;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name' => 'klarna',
            'Action' => 'Reserve',
            'Version' => 1,
            'RequestParameter' => $this->getKlarnaRequestParameters($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getCaptureTransactionBuilder($payment)
    {
        //$group = 1;
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $capturePartial = true;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $totalOrder = $order->getBaseGrandTotal();

        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $currentInvoice = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $services = [
            'Name' => 'klarna',
            'Action' => 'Pay',
            'Version' => 1,
        ];

        // add additional information
        $articles = $this->getAdditionalInformation($payment);

        // always get articles from invoice
        if (isset($currentInvoice)) {
            $articledata = $this->getPayRequestData($currentInvoice, $payment);
            $articles = array_merge($articles, $articledata);
            //$group++;
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);
            $serviceLine = $this->getServiceCostLine($currentInvoice, $includesTax, $this->groupId++);
            if (!empty($serviceLine)) {
                unset($serviceLine[1]);
                unset($serviceLine[3]);
                unset($serviceLine[4]);
                $articles = array_merge($articles, $serviceLine);
                //$group++;
            }
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($currentInvoice, $this->groupId++);

        if (!empty($shippingCosts)) {
            unset($shippingCosts[1]);
            unset($shippingCosts[3]);
            unset($shippingCosts[4]);
            $articles = array_merge($articles, $shippingCosts);
        }

        $services['RequestParameter'] = $articles;

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setAmount($currentInvoiceTotal)
            ->setMethod('TransactionRequest')
            ->setCurrency($this->payment->getOrder()->getOrderCurrencyCode());

        // Partial Capture Settings
        if ($capturePartial) {
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getRefundTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $capturePartial = true;

        $order = $payment->getOrder();

        $totalOrder = $order->getBaseGrandTotal();

        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        }

        $services = [
            'Name'    => 'klarna',
            'Action'  => 'Refund',
            'Version' => 1,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey($payment->getRefundTransactionId());

        // Partial Capture Settings
        if ($capturePartial) {
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
                ->setOriginalTransactionKey($payment->getRefundTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getVoidTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name' => 'klarna',
            'Action' => 'CancelReservation',
            'Version' => 1,
            'RequestParameter' => $this->getCancelReservationData($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    public function getRequestShippingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddress
         */
        $shippingAddress = $payment->getOrder()->getShippingAddress();
        $streetFormat = $this->addressFormatter->formatStreet($shippingAddress->getStreet());
        $shippingSameAsBilling = $this->isAddressDataDifferent($payment);


        $rawPhoneNumber = $shippingAddress->getTelephone();
        if (!is_numeric($rawPhoneNumber) || $rawPhoneNumber == '-') {
            $rawPhoneNumber = $payment->getAdditionalInformation('customer_telephone');
        }

        $phoneNumber = $this->addressFormatter->formatTelephone($rawPhoneNumber, $shippingAddress->getCountryId());

        $shippingData = [
            [
                '_' => $shippingSameAsBilling,
                'Name' => 'ShippingSameAsBilling',
            ],
            [
                '_' => $phoneNumber['clean'],
                'Name' => ($phoneNumber['mobile'] ? 'ShippingCellPhoneNumber' : 'ShippingPhoneNumber'),
            ],
            [
                '_' => $shippingAddress->getCity(),
                'Name' => 'ShippingCity',
            ],
            [
                '_' => $shippingAddress->getCountryId(),
                'Name' => 'ShippingCountry',
            ],
            [
                '_' => $shippingAddress->getEmail(),
                'Name' => 'ShippingEmail',
            ],
            [
                '_' => $shippingAddress->getFirstname(),
                'Name' => 'ShippingFirstName',
            ],
            [
                '_' => $shippingAddress->getLastName(),
                'Name' => 'ShippingLastName',
            ],
            [
                '_' => $shippingAddress->getPostcode(),
                'Name' => 'ShippingPostalCode',
            ],
            [
                '_' => $streetFormat['street'],
                'Name' => 'ShippingStreet',
            ],
        ];


        if (!empty($streetFormat['house_number'])) {
            $shippingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'ShippingHouseNumber',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $shippingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'ShippingHouseNumberSuffix',
            ];
        }

        return $shippingData;
    }

    /**
     * @param $invoice
     * @param $payment
     * @return array
     */
    public function getPayRequestData($invoice, $payment)
    {
        $order = $payment->getOrder();
        $invoiceCollection = $order->getInvoiceCollection();

        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        $articles = array();
        //$group = 1;

        $invoiceItems = $invoice->getAllItems();

        foreach ($invoiceItems as $item) {
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $articles[] = [
                '_' => $item->getSku(),
                'Group' => 'Article',
                'GroupID' => $this->groupId,
                'Name' => 'ArticleNumber',
            ];

            $articles[] = [
                '_' => (int) $item->getQty(),
                'Group' => 'Article',
                'GroupID' => $this->groupId,
                'Name' => 'ArticleQuantity',
            ];

            $this->groupId++;
        }

        $discountline = $this->getDiscountLine($payment, $this->groupId);

        if (false !== $discountline &&
            is_array($discountline) &&
            count($discountline) > 0 &&
            count($invoiceCollection) == 1
        ) {
            unset($discountline[1]);
            unset($discountline[3]);
            unset($discountline[4]);
            $articles = array_merge($articles, $discountline);
            $this->groupId++;
        }

        return $articles;
    }

    /**
     * @param $payment
     *
     * @return array
     */
    public function getCancelReservationData($payment)
    {
        $order = $payment->getOrder();

        $reservationr = [
            [
                '_'    => $order->getBuckarooReservationNumber(),
                'Name' => 'ReservationNumber',
            ]
        ];

        return $reservationr;
    }

    /**
     * {@inheritdoc}
     */
    public function processCustomPostData($payment, $postData)
    {
        $order = $payment->getOrder();

        if ($order->getBuckarooReservationNumber()) {
            return;
        }

        $order->setBuckarooReservationNumber($postData->Services->Service->ResponseParameter->_);
        $order->save();
    }

    /**
     * {@inheritDoc}
     */
    public function canPushInvoice($responseData)
    {
        if (isset($responseData['brq_datarequest'])) {
            return false;
        }

        if (!isset($responseData['brq_datarequest']) && isset($responseData['brq_transactions'])) {
            return true;
        }

        return parent::canPushInvoice($responseData);
    }

    /**
     * @param $payment
     *
     * @return array
     */
    public function getAdditionalInformation($payment)
    {
        $order = $payment->getOrder();

        $additionalinformation = [
            [
                '_' => !$this->checkInvoiceSendByEmail() ? 'true' : 'false',
                'Name' => 'SendByMail',
            ],
            [
                '_' => $this->checkInvoiceSendByEmail() ? 'true' : 'false' ,
                'Name' => 'SendByEmail',
            ],
            [
                '_' => $order->getBuckarooReservationNumber(),
                'Name' => 'ReservationNumber',
            ]
        ];

        return $additionalinformation;
    }

    /**
     * @return bool
     *
     * @throws \TIG\Buckaroo\Exception
     */
    private function checkInvoiceSendByEmail()
    {
        /** @var \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna $klarnaConfig */
        $klarnaConfig = $this->configProviderMethodFactory->get(self::PAYMENT_METHOD_CODE);

        return (string)$klarnaConfig->getInvoiceSendMethod() === 'email';
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['termsCondition'])) {
            $additionalData = $data['additional_data'];
            $this->getInfoInstance()->setAdditionalInformation('termsCondition', $additionalData['termsCondition']);
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_billingName',
                $additionalData['customer_billingName']
            );
            $this->getInfoInstance()->setAdditionalInformation('customer_iban', $additionalData['customer_iban']);

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('d-m-Y'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);

            if (isset($additionalData['selectedBusiness'])
                && $additionalData['selectedBusiness'] == self::BUSINESS_METHOD_B2B
            ) {
                $this->getInfoInstance()->setAdditionalInformation('COCNumber', $additionalData['COCNumber']);
                $this->getInfoInstance()->setAdditionalInformation('CompanyName', $additionalData['CompanyName']);
                $this->getInfoInstance()->setAdditionalInformation(
                    'selectedBusiness',
                    $additionalData['selectedBusiness']
                );
            }

            if (isset($additionalData['customer_telephone'])) {
                $this->getInfoInstance()->setAdditionalInformation(
                    'customer_telephone',
                    $additionalData['customer_telephone']
                );
            }
        }

        return $this;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    public function getKlarnaRequestParameters($payment)
    {
        // First data to set is the billing address data.
        $requestData = $this->getRequestBillingData($payment);

        // Merge the shipping data
        $requestData = array_merge($requestData, $this->getRequestShippingData($payment));

        // Merge the article data; products and fee's
        $requestData = array_merge($requestData, $this->getRequestArticlesData($payment));

        return $requestData;
    }


    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return boolean
     */
    public function isAddressDataDifferent($payment)
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

        if (empty($arrayDifferences)) {
            return "true";
        }

        return "false";
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    public function getRequestBillingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
         */
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $streetFormat = $this->addressFormatter->formatStreet($billingAddress->getStreet());

        $listCountries = Zend_Locale::getTranslationList('territory', 'en_US');

        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);
        $telephone = $this->addressFormatter->formatTelephone($telephone, $billingAddress->getCountryId());

        $birthDayStamp = str_replace('-', '', $payment->getAdditionalInformation('customer_DoB'));
        $billingData = [
            [
                '_' => $telephone['clean'],
                'Name' => ($telephone['mobile'] ? 'BillingCellPhoneNumber' : 'BillingPhoneNumber'),
            ],
            [
                '_' => $billingAddress->getCity(),
                'Name' => 'BillingCity',
            ],
            [
                '_' => $billingAddress->getCountryId(),
                'Name' => 'BillingCountry',
            ],
            [
                '_' => $billingAddress->getEmail(),
                'Name' => 'BillingEmail',
            ],
            [
                '_' => $billingAddress->getFirstname(),
                'Name' => 'BillingFirstName',
            ],
            [
                '_' => $billingAddress->getLastName(),
                'Name' => 'BillingLastName',
            ],
            [
                '_' => $billingAddress->getPostcode(),
                'Name' => 'BillingPostalCode',
            ],
            [
                '_' => $streetFormat['street'],
                'Name' => 'BillingStreet',
            ],
            [
                '_' => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'Gender',
            ],
            [
                '_' => $billingAddress->getCountryId(),
                'Name' => 'OperatingCountry',
            ],
            [
                '_' => $birthDayStamp,
                'Name' => 'Pno',
            ],
            [
                '_' => $listCountries[$billingAddress->getCountryId()],
                'Name' => 'Encoding',
            ]
        ];

        if (!empty($streetFormat['house_number'])) {
            $billingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'BillingHouseNumber',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'BillingHouseNumberSuffix',
            ];
        }

        return $billingData;
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return boolean
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

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        $arrayDiff = array_diff($filteredAddressOne, $filteredAddressTwo);

        return $arrayDiff;
    }

    /**
     * @param $payment
     *
     * @return array
     */
    public function getRequestArticlesData($payment)
    {
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        /**
         * @var \Magento\Eav\Model\Entity\Collection\AbstractCollection|array $cartData
         */

        $cartData = $this->cart->getItems();

        $articles = array();
        $group    = 1;
        $max      = 99;
        $i        = 1;

        foreach ($cartData as $item) {
            if (empty($item) || $item->hasParentItemId()) {
                continue;
            }

            $article = [
                [
                    '_' => $item->getName(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleTitle',
                ],
                [
                    '_' => $item->getSku(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleNumber',
                ],
                [
                    '_' => self::KLARNA_ARTICLE_TYPE_GENERAL,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleType',
                ],
                [
                    '_' => $item->getBasePriceInclTax(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticlePrice',
                ],
                [
                    '_' => $item->getQty(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleQuantity',
                ],
                [
                    '_' => $item->getTaxPercent(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleVat',
                ]
            ];

            $articles = array_merge($articles, $article);
            $group++;

            if ($i > $max) {
                break;
            }
        }

        $requestData = $articles;

        $serviceLine = $this->getServiceCostLine($payment->getOrder(), $includesTax, $group);

        if (!empty($serviceLine)) {
            $requestData = array_merge($articles, $serviceLine);
            $group++;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder(), $group);

        if (!empty($shippingCosts)) {
            $requestData = array_merge($requestData, $shippingCosts);
            $group++;
        }

        $discountline = $this->getDiscountLine($payment, $group);

        if (!empty($discountline)) {
            $requestData = array_merge($requestData, $discountline);
        }

        return $requestData;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $productItem
     * @param                                 $includesTax
     *
     * @return mixed
     */
    public function calculateProductPrice($productItem, $includesTax)
    {
        if ($includesTax) {
            $productPrice = $productItem->getRowTotalInclTax();
        } else {
            $productPrice = $productItem->getRowTotal();
        }

        return $productPrice;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return float|int
     */
    private function getDiscountAmount($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $discount = 0;
        $edition = $this->softwareData->getProductMetaData()->getEdition();

        if ($order->getDiscountAmount() < 0) {
            $discount -= abs((double)$order->getDiscountAmount());
        }

        if ($edition == 'Enterprise' && $order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((double)$order->getCustomerBalanceAmount());
        }

        return $discount;
    }

    /**
     * Get the discount cost lines
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param $group
     *
     * @return array
     */
    public function getDiscountLine($payment, $group)
    {
        $article = [];
        $discount = $this->getDiscountAmount($payment);

        if ($discount >= 0) {
            return $article;
        }

        $article = [
            [
                '_' => 3,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleNumber',
            ],
            [
                '_' => $discount,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticlePrice',
            ],
            [
                '_' => 1,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleQuantity',
            ],
            [
                '_' => 'Discount',
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleTitle',
            ],
            [
                '_' => 0,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleVat',
            ],
        ];

        return $article;
    }

    /**
     * @param OrderInterface $order
     * @param $group
     *
     * @return array
     */
    private function getShippingCostsLine($order, $group)
    {
        $shippingCostsArticle = [];

        if ($order->getShippingAmount() <= 0) {
            return $shippingCostsArticle;
        }

        $request = $this->taxCalculation->getRateRequest(null, null, null);
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingIncludesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX);
        $shippingAmount = $order->getShippingAmount();

        if ($shippingIncludesTax) {
            $shippingAmount += $order->getShippingTaxAmount();
        }

        $shippingCostsArticle = [
            [
                '_' => 2,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleNumber',
            ],
            [
                '_' => $shippingAmount,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticlePrice',
            ],
            [
                '_' => 1,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleQuantity',
            ],
            [
                '_' => 'Verzendkosten',
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleTitle',
            ],
            [
                '_' => $percent,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleVat',
            ],
            [
                '_' => self::KLARNA_ARTICLE_TYPE_SHIPMENTFEE,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleType',
            ]
        ];

        return $shippingCostsArticle;
    }

    /**
     * Get the service cost lines (buckfee)
     *
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     * @param $includesTax
     *
     * @param $group
     * @return   array
     * @internal param $ (int) $latestKey
     */
    public function getServiceCostLine($order, $includesTax, $group)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $buckarooFee = $order->getBuckarooFee();
        $buckarooTax = $order->getBuckarooFeeTaxAmount();

        $items = $order->getItems();

        foreach ($items as $data) {
            $article = [];

            if (false !== $buckarooFee && (double)$buckarooFee > 0) {
                $article = [
                    [
                        '_' => 1,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleNumber',
                    ],
                    [
                        '_' => round($buckarooFee + $buckarooTax, 2),
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticlePrice',
                    ],
                    [
                        '_' => 1,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleQuantity',
                    ],
                    [
                        '_' => 'Servicekosten',
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleTitle',
                    ],
                    [
                        '_' => $this->getTaxPercent($data),
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleVat',
                    ],
                    [
                        '_' => self::KLARNA_ARTICLE_TYPE_HANDLINGFEE,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleType',
                    ]
                ];
            }

            return $article;
        }
    }

    /**
     * @param $data
     * @return string
     */
    private function getTaxPercent($data)
    {
        $taxPercent = $data->getTaxPercent();
        if (!$taxPercent) {
            $taxPercent = $data->getOrderItem()->getTaxPercent();
        }

        return $taxPercent;
    }
}
