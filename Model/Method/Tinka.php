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

use Magento\Catalog\Model\Product\Type;
use Buckaroo\Magento2\Model\Config\Source\TinkaActiveService;

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

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

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

        if (isset($data['additional_data']['customer_DoB'])) {
            $additionalData = $data['additional_data'];

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);
        }

        if (isset($data['additional_data']['customer_telephone'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_telephone',
                $data['additional_data']['customer_telephone']
            );
        }

        return $this;
    }

    private function getPhoneNumber($payment, $billingAddress)
    {

        $telephone = $billingAddress->getTelephone();

        if($telephone !== null) {
            return $telephone;
        }

        $telephone = $payment->getAdditionalInformation('customer_telephone');
        if(!empty($telephone)) {
            return $telephone;
        }
        return '';
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

        $telephone = $this->getPhoneNumber($payment, $billingAddress);

        $billingData = [
            [
                "Name" => "Email",
                "Group" => "BillingCustomer",
                "GroupID" => "",
                "_" => $billingAddress->getEmail()
            ],
            [
                "Name"=> "Initials",
                "Group"=> "",
                "GroupID"=> "",
                "_"=> $this->getInitials($billingAddress)
            ],
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
        $shippingAddress = $this->getShippingAddress($payment);

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

        $dateOfBirth = $payment->getAdditionalInformation('customer_DoB') ?? '01-01-1990';

        $services = [
            'Name'             => 'Tinka',
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
            'RequestParameter' => [
                [
                    '_'    => $this->getActiveService(
                        $payment->getOrder()->getStoreId()
                    ),
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
    protected function getShippingCostsLine($order, $count, &$itemsTotalAmount = 0)
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
    public function getVoidTransactionBuilder($payment)
    {
        return true;
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

    public function getActiveService($storeId = null)
    {
        $activeService = $this->getConfigData('activeservice', $storeId);
        
        if (!in_array($activeService, TinkaActiveService::LIST)) {
            return TinkaActiveService::CREDIT;
        }
        return $activeService;
    }

    public function getInitials($address): string
    {
        return strtoupper(substr($address->getFirstname(), 0, 1)) .
        strtoupper(substr($address->getFirstname(), 0, 1));
        
    }
}
