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

use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Catalog\Model\Product\Type;

class Tinka extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_tinka';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'tinka';

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
    protected $_canAuthorize            = false;

    /**
     * @var bool
     */
    protected $_canCapture              = false;

    /**
     * @var bool
     */
    protected $_canCapturePartial       = false;

    /**
     * @var bool
     */
    protected $_canRefund               = true;

    /**
     * @var bool
     */
    protected $_canVoid                 = true;

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
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @param Calculation                                             $taxCalculation
     * @param Config                                                  $taxConfig
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory       $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory            $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                            $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface      $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                    $logger
     * @param \Magento\Developer\Helper\Data                          $developmentHelper
     * @param \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee          $configProviderBuckarooFee
     * @param AddressFactory                                          $addressFactory
     * @param SoftwareData                                            $softwareData
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param \Buckaroo\Magento2\Gateway\GatewayInterface                  $gateway
     * @param \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory    $transactionBuilderFactory
     * @param \Buckaroo\Magento2\Model\ValidatorFactory                    $validatorFactory
     * @param \Buckaroo\Magento2\Helper\Data                               $helper
     * @param \Magento\Framework\App\RequestInterface                 $request
     * @param \Buckaroo\Magento2\Model\RefundFieldsFactory                 $refundFieldsFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory              $configProviderFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory       $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data                  $priceHelper
     * @param array                                                   $data
     *
     * @throws \Buckaroo\Magento2\Exception
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
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        AddressFactory $addressFactory,
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

        $this->addressFactory  = $addressFactory;
    }

    // @codingStandardsIgnoreEnd
    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['customer_billingName'])) {
            $this->getInfoInstance()->setAdditionalInformation('customer_billingName', $data['additional_data']['customer_billingName']);
        }

        if (isset($data['additional_data']['customer_gender'])){
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $data['additional_data']['customer_gender']);
        }

        if (isset($data['additional_data']['customer_DoB'])) {
            $additionalData = $data['additional_data'];

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);
        }

        if (isset($data['additional_data']['buckaroo_skip_validation'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'buckaroo_skip_validation',
                $data['additional_data']['buckaroo_skip_validation']
            );
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestBillingData($payment){

        $billingAddress = $payment->getOrder()->getBillingAddress();
        $billingStreetFormat   = $this->formatStreet($billingAddress->getStreet());

        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);

        $billingData = [
            [
                "Name" => "Email",
                "Group" => "BillingCustomer",
                "GroupID" => "",
                "_" => $billingAddress->getEmail()
            ],
//                [
//                    "Name"=> "PrefixLastName",
//                    "Group"=> "BillingCustomer",
//                    "GroupID"=> "",
//                    "_"=> $billingAddress->getPrefix()
//                ],
            [
                "Name"=> "City",
                "Group"=> "BillingCustomer",
                "GroupID"=> "",
                "_"=> $billingAddress->getCity()
            ],
            [
                "Name"=> "Country",
                "Group"=> "BillingCustomer",
                "GroupID"=> "",
                "_"=> $billingAddress->getCountryId()
            ],
            [
                "Name"=> "PostalCode",
                "Group"=> "BillingCustomer",
                "GroupID"=> "",
                "_"=> $billingAddress->getPostcode()
            ],
            [
                "Name"=> "Street",
                "Group"=> "BillingCustomer",
                "GroupID"=> "",
                "_"=> $billingStreetFormat['street']
            ],
            [
                "Name"=> "FirstName",
                "Group"=> "",
                "GroupID"=> "",
                "_"=> $billingAddress->getFirstname()
            ],
            [
                "Name"=> "LastName",
                "Group"=> "",
                "GroupID"=> "",
                "_"=> $billingAddress->getLastname()
            ],
        ];

        if (!empty($telephone)) {
            $billingData[] = [
                "Name"=> "Phone",
                "Group"=> "BillingCustomer",
                "GroupID"=> "",
                "_"=> $telephone
            ];
        }

        if (!empty($billingStreetFormat['house_number'])) {
            $billingData[] = [
                '_'    => $billingStreetFormat['house_number'],
                'Name' => 'StreetNumber',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (!empty($billingStreetFormat['number_addition'])) {
            $billingData[] = [
                '_'    => $billingStreetFormat['number_addition'],
                'Name' => 'StreetNumberAdditional',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (!$this->isAddressDataDifferent($payment)) {
//            $billingData[] = [
            $billingData[] = [
                "Name"=> "City",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $billingAddress->getCity()
            ];

            $billingData[] = [
                "Name"=> "PostalCode",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $billingAddress->getPostcode()
            ];

            $billingData[] = [
                "Name"=> "StreetNumber",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $billingStreetFormat['house_number']
            ];
            $billingData[] = [
                "Name"=> "Street",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $billingStreetFormat['street']
            ];
            $billingData[] = [
                "Name"=> "StreetNumberAdditional",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $billingStreetFormat['number_addition']
            ];
        }
        return $billingData;
    }

    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return boolean
     */
    public function isAddressDataDifferent($payment)
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress  = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

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

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        $arrayDiff = array_diff($filteredAddressOne, $filteredAddressTwo);

        return $arrayDiff;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestShippingData($payment)
    {
        $order = $payment->getOrder();
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddress
         */
        $shippingAddress = $payment->getOrder()->getShippingAddress();
        $postNLPakjeGemakAddress = $this->getPostNLPakjeGemakAddressInQuote($order->getQuoteId());

        if (!empty($postNLPakjeGemakAddress) && !empty($postNLPakjeGemakAddress->getData())) {
            $shippingAddress = $postNLPakjeGemakAddress;
        }

        $shippingStreetFormat   = $this->formatStreet($shippingAddress->getStreet());

        $shippingData = [
            [
                "Name"=> "ExternalName",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> ($shippingAddress->getFirstname() ?? '') . ' ' . ($shippingAddress->getLastname() ?? '')
            ],
            [
                "Name"=> "Phone",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingAddress->getTelephone()
            ],
            [
                "Name"=> "City",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingAddress->getCity()
            ],
            [
                "Name"=> "Country",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingAddress->getCountryId()
            ],
            [
                "Name"=> "PostalCode",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingAddress->getPostcode()
            ],
            [
                "Name"=> "StreetNumber",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingStreetFormat['house_number']
            ],
            [
                "Name"=> "Street",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingStreetFormat['street']
            ],
            [
                "Name"=> "StreetNumberAdditional",
                "Group"=> "ShippingCustomer",
                "GroupID"=> "",
                "_"=> $shippingStreetFormat['number_addition']
            ],
        ];

        return $shippingData;
    }

    public function updateShippingAddressByDpdParcel($quote, &$requestData)
    {
        $this->logger2->addDebug(__METHOD__.'|1|');

        $fullStreet = $quote->getDpdStreet();
        $postalCode = $quote->getDpdZipcode();
        $city = $quote->getDpdCity();
        $country = $quote->getDpdCountry();

        if (!$fullStreet && $quote->getDpdParcelshopId()) {
            $this->logger2->addDebug(__METHOD__.'|2|');
            $this->logger2->addDebug(var_export($_COOKIE, true));

            $fullStreet = $_COOKIE['dpd-selected-parcelshop-street'] ?? '';
            $postalCode = $_COOKIE['dpd-selected-parcelshop-zipcode'] ?? '';
            $city = $_COOKIE['dpd-selected-parcelshop-city'] ?? '';
            $country = $_COOKIE['dpd-selected-parcelshop-country'] ?? '';
        }

        $matches = false;
        if ($fullStreet && preg_match('/(.*)\s(.+)$/', $fullStreet, $matches)) {
            $this->logger2->addDebug(__METHOD__.'|3|');

            $street = $matches[1];
            $streetHouseNumber = $matches[2];

            $mapping = [
                ['Street', $street],
                ['PostalCode', $postalCode],
                ['City', $city],
                ['Country', $country],
                ['StreetNumber', $streetHouseNumber],
            ];

            $this->logger2->addDebug(var_export($mapping, true));

            foreach ($mapping as $mappingItem) {
                if (!empty($mappingItem[1])) {
                    $found = false;
                    foreach ($requestData as $key => $value) {
                        if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                            if ($requestData[$key]['Name'] == $mappingItem[0]) {
                                $requestData[$key]['_'] = $mappingItem[1];
                                $found = true;
                            }
                        }
                    }
                    if (!$found) {
                        $requestData[] = [
                            '_'    => $mappingItem[1],
                            'Name' => $mappingItem[0],
                            'Group' => 'ShippingCustomer',
                            'GroupID' =>  '',
                        ];
                    }
                }
            }

            foreach ($requestData as $key => $value) {
                if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                    if ($requestData[$key]['Name'] == 'StreetNumberAdditional') {
                        unset($requestData[$key]);
                    }
                }
            }

        }
    }

    public function updateShippingAddressByDhlParcel($servicePointId, &$requestData)
    {
        $this->logger2->addDebug(__METHOD__.'|1|');

        $matches = [];
        if (preg_match('/^(.*)-([A-Z]{2})-(.*)$/', $servicePointId, $matches)) {
            $curl = $this->objectManager->get('Magento\Framework\HTTP\Client\Curl');
            $curl->get('https://api-gw.dhlparcel.nl/parcel-shop-locations/'.$matches[2].'/' . $servicePointId);
            if (
                ($response = $curl->getBody())
                &&
                ($parsedResponse = @json_decode($response))
                &&
                !empty($parsedResponse->address)
            ) {
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                        $mapping = [
                            ['Street', 'street'],
                            ['PostalCode', 'postalCode'],
                            ['City', 'city'],
                            ['Country', 'countryCode'],
                            ['StreetNumber', 'number'],
                        ];
                        foreach ($mapping as $mappingItem) {
                            if (($requestData[$key]['Name'] == $mappingItem[0]) && (!empty($parsedResponse->address->{$mappingItem[1]}))) {
                                $requestData[$key]['_'] = $parsedResponse->address->{$mappingItem[1]};
                            }
                        }

                    }
                }
            }
        }
    }

    /**
     * Check if there is a "pakjegemak" address stored in the quote by this order.
     *
     * @param int $quoteId
     *
     * @return array|\Magento\Quote\Model\Quote\Address
     */
    public function getPostNLPakjeGemakAddressInQuote($quoteId)
    {
        $quoteAddress = $this->addressFactory->create();

        $collection = $quoteAddress->getCollection();
        $collection->addFieldToFilter('quote_id', $quoteId);
        $collection->addFieldToFilter('address_type', 'pakjegemak');
        // @codingStandardsIgnoreLine
        return $collection->setPageSize(1)->getFirstItem();
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $serviceAction = 'Pay';

        $requestData = $this->getRequestBillingData($payment);

        if ($this->isAddressDataDifferent($payment)) {
            $requestData = array_merge($requestData, $this->getRequestShippingData($payment));
        }

        $this->logger2->addDebug(__METHOD__.'|1|');
        $this->logger2->addDebug(var_export($payment->getOrder()->getShippingMethod(), true));

        if ($payment->getOrder()->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quoteFactory = $this->objectManager->create('\Magento\Quote\Model\QuoteFactory');
            $quote = $quoteFactory->create()->load($payment->getOrder()->getQuoteId());
            $this->updateShippingAddressByDpdParcel($quote, $requestData);
        }

        if (
            ($payment->getOrder()->getShippingMethod() == 'dhlparcel_servicepoint')
            &&
            $payment->getOrder()->getDhlparcelShippingServicepointId()
        ) {
            $this->updateShippingAddressByDhlParcel(
                $payment->getOrder()->getDhlparcelShippingServicepointId(), $requestData
            );
        }

        $gender = $payment->getAdditionalInformation('customer_gender');

        $dateOfBirth = $payment->getAdditionalInformation('customer_DoB') ?? '01-01-1990';

        $services = [
            'Name'             => 'Tinka',
            'Action'           => $serviceAction,
            'RequestParameter' => [
                [
                    '_'    => 'Credit',
                    'Name' => 'PaymentMethod',
                    "Group" => "",
                    "GroupID" => "",
                ],
                [
                    "Name" => "DeliveryMethod",
                    "Group" => "",
                    "GroupID" => "",
                    "_" => "ShippingPartner"
                ],
                [
                    "Name" => "DateOfBirth",
                    "Group" => "",
                    "GroupID" => "",
                    "_" => $dateOfBirth
                ],
//                [
//                    "Name"=> "Initials",
//                    "GroupType"=> "",
//                    "GroupID"=> "",
//                    "_"=> $billingAddress->get
//                ],
                [
                    "Name"=> "Gender",
                    "GroupType"=> "",
                    "GroupID"=> "",
                    "_"=> $gender
                ],
            ],
        ];

        $services['RequestParameter'] = array_merge($services['RequestParameter'], $requestData);
        $services['RequestParameter'] = array_merge($services['RequestParameter'], $this->getRequestArticlesData($payment));

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
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
    public function getRequestArticlesData($payment)
    {
        // Set loop variables
        $articles = [];
        $count = 1;
        $countOrderPrice = 0;

        $orderTotal = $payment->getData('amount_ordered');

        /**
         * @var \Magento\Eav\Model\Entity\Collection\AbstractCollection|array $cartData
         */
        $cartData = $this->objectManager->create('Magento\Checkout\Model\Cart')->getItems();

        /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
        foreach ($cartData as $item) {

            if (empty($item)
                || $item->getRowTotalInclTax() == 0
            ) {
                continue;
            }

            //Skip bundles which have dynamic pricing on (0 = yes, 1 = no), because the underlying simples are also in the quote
            if ($item->getProductType() == Type::TYPE_BUNDLE
                && $item->getProduct()->getCustomAttribute('price_type')
                && $item->getProduct()->getCustomAttribute('price_type')->getValue() == 0
            ) {
                continue;
            }

            $itemDiscount = $item->getDiscountAmount() / $item->getQty();
            $itemTax = $item->getTaxAmount() / $item->getQty();

            $itemPrice = ( floor(($item->getPrice() + $itemTax - $itemDiscount) * 100) / 100 );

            $article = $this->getArticleArrayLine(
                $count,
                (int) $item->getQty() . ' x ' . $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $itemPrice
            );

            $countOrderPrice += $itemPrice * $item->getQty();

            $articles = array_merge($articles, $article);
            $count++;
        }

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder());

        if (!empty($serviceLine)) {
            $articles = array_merge($articles, $serviceLine);
            $countOrderPrice += $payment->getOrder()->getBaseBuckarooFee() + $payment->getOrder()->getBuckarooFeeTaxAmount();
            $count++;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder(), $count);

        if (!empty($shippingCosts)) {
            $articles = array_merge($articles, $shippingCosts);
            $countOrderPrice += $payment->getOrder()->getShippingAmount() + $payment->getOrder()->getShippingTaxAmount();
            $count++;
        }

        // Add remaining price after rounding
        if ($orderTotal !== $countOrderPrice) {
            $remainingPrice = $this->getArticleArrayLine(
                $count,
                'Remaining price',
                'remaining_price',
                1,
                round($orderTotal - $countOrderPrice, 2)
            );
            $articles = array_merge($articles, $remainingPrice);
        }

        $requestData = $articles;

        return $requestData;
    }

    /**
     * @param $street
     *
     * @return array
     */
    public function formatStreet($street)
    {
        $street = implode(' ', $street);

        $format = [
            'house_number'    => '',
            'number_addition' => '',
            'street'          => $street
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['street']       = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['street']          = trim($matches[1]);
                    $format['house_number']    = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }

    /**
     * @param $latestKey
     * @param $articleDescription
     * @param $articleId
     * @param $articleQuantity
     * @param $articleUnitPrice
     * @param $articleVat
     *
     * @return array
     */
    public function getArticleArrayLine(
        $latestKey,
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice
    )
    {
        $article = [
            [
                '_' => $articleDescription,
                'Name' => 'Description',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => $articleId,
                'Name' => 'UnitCode',
                'Group' => 'Article',
                'GroupID' => $latestKey,
            ],
            [
                '_' => $articleQuantity,
                'Name' => 'Quantity',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_' => $articleUnitPrice,
                'Name' => 'UnitGrossPrice',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
        ];

        return $article;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @param $count
     * @return array
     */
    private function getShippingCostsLine($order, $count)
    {
        $shippingCostsArticle = [];

        if ($order->getShippingAmount() <= 0) {
            return $shippingCostsArticle;
        }

        $shippingAmount = $order->getShippingAmount() + $order->getShippingTaxAmount();

        $shippingCostsArticle = $this->getArticleArrayLine(
            $count,
            'Shipping fee',
            1,
            1,
            $shippingAmount
        );

        return $shippingCostsArticle;
    }

    /**
     * Get the service cost lines (buckfee)
     *
     * @param (int)                                                                              $latestKey
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     * @param $includesTax
     *
     * @return   array
     * @internal param $ (int) $latestKey
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getServiceCostLine($latestKey, $order)
    {
        $store = $order->getStore();
        $buckarooFeeLine = $order->getBaseBuckarooFee() + $order->getBuckarooFeeTaxAmount();

        $article = [];

        if (false !== $buckarooFeeLine && (double)$buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                $latestKey,
                'Servicekosten',
                1,
                1,
                round($buckarooFeeLine, 2)
            );
        }

        return $article;
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
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'    => 'Tinka',
            'Action'  => 'Refund',
            'Version' => 1,
        ];

        $requestParams = $this->addExtraFields($this->_code);
        $services = array_merge($services, $requestParams);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            )
            ->setChannel('CallCenter');

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }
}
