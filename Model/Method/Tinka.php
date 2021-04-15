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

use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

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

        if (isset($data['additional_data']['customer_gender'])) {
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
    public function getRequestBillingData($payment)
    {

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

            if (!empty($billingStreetFormat['number_addition'])) {
                $billingData[] = [
                    "Name" => "StreetNumberAdditional",
                    "Group" => "ShippingCustomer",
                    "GroupID" => "",
                    "_" => $billingStreetFormat['number_addition']
                ];
            }
        }
        return $billingData;
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
            ]
        ];

        if (!empty($shippingStreetFormat['number_addition'])) {
            $shippingData[] = [
                "Name" => "StreetNumberAdditional",
                "Group" => "ShippingCustomer",
                "GroupID" => "",
                "_" => $shippingStreetFormat['number_addition']
            ];
        }

        return $shippingData;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $requestData = $this->getRequestBillingData($payment);

        if ($this->isAddressDataDifferent($payment)) {
            $requestData = array_merge($requestData, $this->getRequestShippingData($payment));
        }

        $this->logger2->addDebug(__METHOD__.'|1|');
        $this->logger2->addDebug(var_export($payment->getOrder()->getShippingMethod(), true));

        if ($payment->getOrder()->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
            $this->updateShippingAddressByDpdParcel($quote, $requestData);
        }

        if (($payment->getOrder()->getShippingMethod() == 'dhlparcel_servicepoint')
            &&
            $payment->getOrder()->getDhlparcelShippingServicepointId()
        ) {
            $this->updateShippingAddressByDhlParcel(
                $payment->getOrder()->getDhlparcelShippingServicepointId(),
                $requestData
            );
        }

        $gender = $payment->getAdditionalInformation('customer_gender');

        $dateOfBirth = $payment->getAdditionalInformation('customer_DoB') ?? '01-01-1990';

        $services = [
            'Name'             => 'Tinka',
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
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

        $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

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
                $item->getName(),
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
        if (round($orderTotal - $countOrderPrice, 2) > 0) {
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
        $articleUnitPrice,
        $articleVat = ''
    ) {
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

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $shippingCostsArticle = $this->getArticleArrayLine(
            $count,
            'Shipping fee',
            1,
            1,
            $shippingAmount
        );

        return $shippingCostsArticle;
    }

    protected function getTaxCategory($order)
    {
        return '';
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
