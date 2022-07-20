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

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Service\Formatter\AddressFormatter;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Checkout\Model\Cart;
use Zend_Locale;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\AddressFactory;

class Klarnakp extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_klarnakp';

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    /** Klarnakp Article Types */
    const KLARNAKP_ARTICLE_TYPE_GENERAL = 'General';
    const KLARNAKP_ARTICLE_TYPE_HANDLINGFEE = 'HandlingFee';
    const KLARNAKP_ARTICLE_TYPE_SHIPMENTFEE = 'ShipmentFee';

    /**
     * Business methods that will be used in klarna.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'klarnakp';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

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
    public $closeAuthorizeTransaction   = false;

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
     * @param \Buckaroo\Magento2\Gateway\GatewayInterface $gateway
     * @param \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory
     * @param \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory
     * @param \Buckaroo\Magento2\Helper\Data $helper
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
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
        Cart $cart,
        AddressFormatter $addressFormatter,
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

        $this->context = $context;
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
            'Name' => 'klarnakp',
            'Action' => 'Reserve',
            'Version' => 1,
            'RequestParameter' => $this->getKlarnakpRequestParameters($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    protected function getCaptureTransactionBuilderVersion()
    {
        return 1;
    }

    protected function getCaptureTransactionBuilderAction()
    {
        return 'Pay';
    }

    protected function getCaptureTransactionBuilderArticles($payment, $currentInvoice, $numberOfInvoices)
    {
        // add additional information
        $articles = $this->getAdditionalInformation($payment);

        // always get articles from invoice
        if (isset($currentInvoice)) {
            $articledata = $this->getPayRequestData($currentInvoice, $payment);
            $articles = array_merge($articles, $articledata);
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $serviceLine = $this->getServiceCostLine($this->groupId++, $currentInvoice);
            if (!empty($serviceLine)) {
                unset($serviceLine[0]);
                unset($serviceLine[3]);
                unset($serviceLine[4]);
                $articles = array_merge($articles, $serviceLine);
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

        return $articles;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
    {
        $transactionBuilder = parent::getRefundTransactionBuilder($payment);
        $this->getRefundTransactionBuilderPartialSupport($payment, $transactionBuilder);
        return $transactionBuilder;
    }

    protected function getRefundTransactionBuilderServices($payment, &$services)
    {
        $this->getRefundTransactionBuilderServicesAdd($payment, $services);
    }

    protected function getRefundTransactionBuilderChannel()
    {
        return '';
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getVoidTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name' => 'klarnakp',
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
        $isAbsentShippingAddress = false;
        $shippingAddress = $this->getShippingAddress($payment, $isAbsentShippingAddress);
        if ($isAbsentShippingAddress) {
            $shippingSameAsBilling = "true";
        } else {
            $shippingSameAsBilling = $this->isAddressDataDifferent($payment) ? "false" : "true";
        }

        $streetFormat = $this->addressFormatter->formatStreet($shippingAddress->getStreet());

        $rawPhoneNumber = $shippingAddress->getTelephone();
        if (!is_numeric($rawPhoneNumber) || $rawPhoneNumber == '-') {
            $rawPhoneNumber = $payment->getAdditionalInformation('customer_telephone');
        }

        $shippingData = [
            [
                '_' => $shippingSameAsBilling,
                'Name' => 'ShippingSameAsBilling',
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
        $this->logger2->addDebug(__METHOD__.'|1|');

        $order = $payment->getOrder();
        $invoiceCollection = $order->getInvoiceCollection();

        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $articles = [];

        $invoiceItems = $invoice->getAllItems();

        $qtys = [];
        foreach ($invoiceItems as $item) {
            $this->logger2->addDebug(
                __METHOD__.'|2|' . var_export([$item->getSku(), $item->getOrderItem()->getParentItemId()], true)
            );
            if (empty($item)
                || $item->getOrderItem()->getParentItemId()
                || $this->calculateProductPrice($item, $includesTax) == 0
            ) {
                continue;
            }

            $qtys[$item->getSku()] = [
                'qty' => (int) $item->getQty(),
                'price' => $this->calculateProductPrice($item, $includesTax),
            ];
        }

        $this->logger2->addDebug(var_export($qtys, true));

        foreach ($qtys as $sku => $item) {

            $articles[] = [
                    '_' => $sku,
                    'Group' => 'Article',
                    'GroupID' => $this->groupId,
                    'Name' => 'ArticleNumber',
            ];
            $articles[] = [
                    '_' => $item['qty'],
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

        if (isset($postData->Services) && count($postData->Services->Service->ResponseParameter) > 0) {
            $order->setBuckarooReservationNumber($postData->Services->Service->ResponseParameter->_);
            $order->save();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function canPushInvoice($responseData): bool
    {
        if (!empty($responseData->getDatarequest())) {
            return false;
        }

        if (empty($responseData->getDatarequest()) && !empty($responseData->getTransactions())) {
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
                '_' => $order->getBuckarooReservationNumber(),
                'Name' => 'ReservationNumber',
            ]
        ];

        return $additionalinformation;
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
    public function getKlarnakpRequestParameters($payment)
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

        $this->logger2->addDebug(__METHOD__.'|1|');
        $this->logger2->addDebug(var_export([$billingAddress->getStreet(), $streetFormat], true));

        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);
        $telephone = $this->addressFormatter->formatTelephone($telephone, $billingAddress->getCountryId());

        $billingData = [
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
                '_' => $billingAddress->getCountryId(),
                'Name' => 'OperatingCountry',
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

        if (!empty($telephone['orginal'])) {
            $billingData[] = [
                '_'    => $telephone['orginal'],
                'Name' => 'BillingPhoneNumber',
            ];
        }

        return $billingData;
    }

    /**
     * @param $payment
     *
     * @return array
     */
    public function getRequestArticlesData($payment)
    {
        $this->logger2->addDebug(__METHOD__.'|1|');

        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        $articles = [];
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
                    '_' => self::KLARNAKP_ARTICLE_TYPE_GENERAL,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleType',
                ],
                [
                    '_' => $this->calculateProductPrice($item, $includesTax),
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
                    '_' => $item->getTaxPercent() ?? 0,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleVat',
                ]
            ];

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd
            $group++;

            if ($i > $max) {
                break;
            }
        }

        $requestData = $articles;

        $serviceLine = $this->getServiceCostLine($group, $payment->getOrder());

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
    protected function getShippingCostsLine($order, $group, &$itemsTotalAmount = 0)
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $request = $this->taxCalculation->getRateRequest(null, null, null);
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

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
                '_' => self::KLARNAKP_ARTICLE_TYPE_SHIPMENTFEE,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleType',
            ]
        ];

        return $shippingCostsArticle;
    }

    public function getArticleArrayLine(
        $latestKey,
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ) {
        $article = [
            [
                '_'       => $articleDescription,
                'Name'    => 'ArticleTitle',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => $articleId,
                'Name'    => 'ArticleNumber',
                'Group' => 'Article',
                'GroupID' => $latestKey,
            ],
            [
                '_'       => $articleQuantity,
                'Name'    => 'ArticleQuantity',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => $articleUnitPrice,
                'Name'    => 'ArticlePrice',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => $articleVat,
                'Name'    => 'ArticleVat',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => self::KLARNAKP_ARTICLE_TYPE_HANDLINGFEE,
                'Group' => 'Article',
                'GroupID' => $latestKey,
                'Name' => 'ArticleType',
            ]
        ];

        return $article;
    }

    protected function getTaxCategory($order)
    {
        $items = $order->getItems();

        foreach ($items as $data) {
            return $this->getTaxPercent($data);
        }
    }

    /**
     * @param $data
     * @return string
     */
    private function getTaxPercent($data)
    {
        $taxPercent = 0;

        if ($data) {
            $taxPercent = $data->getTaxPercent();
            if (!$taxPercent) {
                if ($data->getOrderItem()) {
                    $taxPercent = $data->getOrderItem()->getTaxPercent();
                }
            }
        }

        return $taxPercent;
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
