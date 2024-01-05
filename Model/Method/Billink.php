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

use Magento\Tax\Model\Config;
use Magento\Tax\Model\Calculation;
use Magento\Catalog\Model\Product\Type;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;

class Billink extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_billink';

    /**
     * Max articles that can be handled by billink
     */
    const BILLINK_MAX_ARTICLE_COUNT = 99;

    /**
     * Business methods that will be used in afterpay.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;
    const BUSINESS_METHOD = 'payment/buckaroo_magento2_billink/business';

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    const BILLINK_PAYMENT_METHOD_NAME = 'Billink';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'billink';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

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
    public $usesRedirect                = false;

    /**
     * @var null
     */
    public $remoteAddress               = null;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction   = false;

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        $additionalData = $data['additional_data'];

        if (isset($additionalData['customer_billingName'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_billingName',
                $additionalData['customer_billingName']
            );
        }

        if (isset($additionalData['customer_gender'])) {
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
        }

        if (isset($additionalData['customer_chamberOfCommerce'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_chamberOfCommerce',
                $additionalData['customer_chamberOfCommerce']
            );
        }

        if (isset($additionalData['customer_VATNumber'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_VATNumber',
                $additionalData['customer_VATNumber']
            );
        }

        if (isset($additionalData['customer_DoB'])) {
            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('d-m-Y'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);
        }

        if (isset($additionalData['customer_telephone'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_telephone',
                $additionalData['customer_telephone']
            );
        }

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
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return static::BILLINK_PAYMENT_METHOD_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'Billink',
            'Action'           => 'Pay',
            'Version'          => 1,
            'RequestParameter' => $this->getPaymentRequestParameters($payment),
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation('skip_push', 1);

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => 'Authorize',
            'RequestParameter' => $this->getPaymentRequestParameters($payment),
        ];

        $transactionBuilder->setOrder($payment->getOrder())->setServices($services)->setMethod('TransactionRequest');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation('skip_push', 1);

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'   => $this->getPaymentMethodName($payment),
            'Action' => 'CancelAuthorize',
        ];

        $originalTrxKey = $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        $transactionBuilder->setOrder($payment->getOrder())
            ->setAmount(0)
            ->setType('void')
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey($originalTrxKey);

        $parentTrxKey = $payment->getParentTransactionId();

        if ($parentTrxKey && strlen($parentTrxKey) > 0 && $parentTrxKey != $originalTrxKey) {
            $transactionBuilder->setOriginalTransactionKey($parentTrxKey);
        }

        return $transactionBuilder;
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

    /**
     * @param $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
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

        // Set loop variables
        $articles = [];
        $count    = 1;
        $bundleProductQty = 0;

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($cartData as $item) {

            if (empty($item)
                || $item->getRowTotalInclTax() == 0
            ) {
                continue;
            }

            //Skip bundles which have dynamic pricing on (0 = yes,1 = no) - the underlying simples are also in the quote
            if ($item->getProductType() == Type::TYPE_BUNDLE
                && $item->getProduct()->getCustomAttribute('price_type')
                && $item->getProduct()->getCustomAttribute('price_type')->getValue() == 0
            ) {
                $bundleProductQty = $item->getQty();
                continue;
            }

            if (!$item->getParentItemId()) {
                $bundleProductQty = 0;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $bundleProductQty ? $bundleProductQty : $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getTaxPercent()
            );

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreStart

            if ($count < self::BILLINK_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder());

        if (!empty($serviceLine)) {
            $articles = array_merge($articles, $serviceLine);
            $count++;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder(), $count);

        if (!empty($shippingCosts)) {
            $articles = array_merge($articles, $shippingCosts);
            $count++;
        }

        $discountline = $this->getDiscountLine($count, $payment);

        if (!empty($discountline)) {
            $articles = array_merge($articles, $discountline);
        }

        return $articles;
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getInvoiceArticleData($invoice)
    {
        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        // Set loop variables
        $articles = [];
        $count    = 1;

        /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {
            if (empty($item) || $item->getRowTotalInclTax() == 0) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getOrderItem()->getTaxPercent()
            );

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd

            // Capture calculates discount per order line
            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    $count,
                    'Korting op ' . $item->getName(),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    0
                );
                // @codingStandardsIgnoreStart
                $articles = array_merge($articles, $article);
                // @codingStandardsIgnoreEnd
            }

            if ($count < self::BILLINK_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $requestData = $articles;

        return $requestData;
    }

    /**
     * @param $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getCreditmemoArticleData($payment)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $articles = [];
        $count = 1;
        $itemsTotalAmount = 0;

        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            if (empty($item) || $item->getRowTotalInclTax() == 0) {
                continue;
            }

            $refundType = $this->getRefundType($count);
            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $refundType);
            // @codingStandardsIgnoreEnd

            $prodPrice = $this->calculateProductPrice($item, $includesTax);

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $prodPrice - round($item->getDiscountAmount() / $item->getQty(), 2),
                $item->getOrderItem()->getTaxPercent()
            );

            $itemsTotalAmount += $item->getQty() * ($prodPrice - $item->getDiscountAmount());

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd

            if ($count < self::BILLINK_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        // hasCreditmemos returns since 2.2.6 true or false.
        // The current creditmemo is still "in progress" and thus has yet to be saved.
        if (count($articles) > 0 && !$payment->getOrder()->hasCreditmemos()) {
            $serviceLine = $this->getServiceCostLine($count, $creditmemo, $itemsTotalAmount);
            $articles = array_merge($articles, $serviceLine);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
            $count++;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($creditmemo, $count, $itemsTotalAmount);
        if (!empty($shippingCosts)) {
            $articles = array_merge($articles, $shippingCosts);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
            $count++;
        }

        //Add diff line
        if ($creditmemo->getBaseGrandTotal() != $itemsTotalAmount) {
            $diff = $creditmemo->getBaseGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($count, $diff);
            $articles = array_merge($articles, $diffLine);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
        }

        return $articles;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @param $count
     * @return array
     */
    protected function getShippingCostsLine333($order, $count, &$itemsTotalAmount = 0)
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
                '_'       => 'Shipping fee',
                'Name'    => 'Description',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => number_format($shippingAmount, 4, '.', ''),
                'Name'    => 'GrossUnitPriceIncl',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => (int)$percent,
                'Name'    => 'VatPercentage',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => '1',
                'Name'    => 'Quantity',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => '1',
                'Name'    => 'Identifier',
                'Group'   => 'Article',
                'GroupID' => $count,
            ]
        ];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    /**
     * Get the discount cost lines
     *
     * @param (int)                                                                              $latestKey
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getDiscountLine($latestKey, $payment)
    {
        $article = [];
        $discount = $this->getDiscountAmount($payment);

        if ($discount >= 0) {
            return $article;
        }

        $article = $this->getArticleArrayLine(
            $latestKey,
            'Korting',
            1,
            1,
            round($discount, 2),
            0
        );

        return $article;
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
                '_'       => $articleDescription,
                'Name'    => 'Description',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => $articleId,
                'Name'    => 'Identifier',
                'Group' => 'Article',
                'GroupID' => $latestKey,
            ],
            [
                '_'       => $articleQuantity,
                'Name'    => 'Quantity',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => number_format($articleUnitPrice, 4, '.', ''),
                'Name'    => 'GrossUnitPriceIncl',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => (int)$articleVat,
                'Name'    => 'VatPercentage',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ]
        ];

        return $article;
    }
    
    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return string|null
     */
    private function getBirthDate($payment)
    {
        $birth = $payment->getAdditionalInformation('customer_DoB');
        
        if(!is_string($birth) || strlen(trim($birth)) === 0) {
            return null;
        }

        $birthDayStamp = date(
            "d-m-Y",
            strtotime(str_replace('/', '-', $birth))
        );

        if($birthDayStamp === false) {
            return null;
        }
        return $birthDayStamp;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestBillingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
         */
        $order = $payment->getOrder();
        $billingAddress = $order->getBillingAddress();
        $streetFormat   = $this->formatStreet($billingAddress->getStreet());

        $birthDayStamp = $this->getBirthDate($payment);

        $chamberOfCommerce = $payment->getAdditionalInformation('customer_chamberOfCommerce');
        $VATNumber = $payment->getAdditionalInformation('customer_VATNumber');
        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);

        $category = $this->helper->checkCustomerGroup('buckaroo_magento2_billink') ? 'B2B' : 'B2C'; //1

        switch ($payment->getAdditionalInformation('customer_gender')) {
            case 'male':
                $gender = 'Male';
                break;
            case 'female':
                $gender = 'Female';
                break;
            case 'unknown':
                $gender = 'Unknown';
                break;
        }

        $GroupID = 1;
        $billingData = [
            [
                '_'    => $category,
                'Name' => 'Category',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getFirstname(),
                'Name' => 'FirstName',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => strtoupper(substr($billingAddress->getFirstname(), 0, 1)),
                'Name' => 'Initials',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getLastName(),
                'Name' => 'LastName',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $this->getCareOf($billingAddress),
                'Name' => 'CareOf',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'Street',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getPostcode(),
                'Name' => 'PostalCode',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getCity(),
                'Name' => 'City',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getCountryId(),
                'Name' => 'Country',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $billingAddress->getEmail(),
                'Name' => 'Email',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ],
        ];

        if (!empty($telephone)) {
            $billingData[] = [
                '_'    => $telephone,
                'Name' => 'MobilePhone',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($streetFormat['house_number'])) {
            $billingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'StreetNumber',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'StreetNumberAdditional',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($chamberOfCommerce)) {
            $billingData[] = [
                '_'    => $chamberOfCommerce,
                'Name' => 'ChamberOfCommerce',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($VATNumber)) {
            $billingData[] = [
                '_'    => $VATNumber,
                'Name' => 'VATNumber',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($gender)) {
            $billingData[] = [
                '_'    => $gender,
                'Name' => 'Salutation',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($birthDayStamp)) {
            $billingData[] = [
                '_'    => $birthDayStamp,
                'Name' => 'BirthDate',
                'Group' => 'BillingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        return $billingData;
    }

    /**
     * Get company name or customer name for care of field
     *
     * @param OrderAddressInterface $address
     *
     * @return string
     */
    private function getCareOf(OrderAddressInterface $address): string
    {
        $company = $address->getCompany();

        if ($company !== null && strlen(trim($company)) > 0) {
            return $company;
        }

        return $address->getFirstname() . ' ' .$address->getLastName();
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

        $streetFormat    = $this->formatStreet($shippingAddress->getStreet());

        $GroupID = 2;
        $shippingData = [
            [
                '_'    => $shippingAddress->getFirstname(),
                'Name' => 'FirstName',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => strtoupper(substr($shippingAddress->getFirstname(), 0, 1)),
                'Name' => 'Initials',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $shippingAddress->getLastName(),
                'Name' => 'LastName',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $this->getCareOf($shippingAddress),
                'Name' => 'CareOf',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'Street',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $shippingAddress->getPostcode(),
                'Name' => 'PostalCode',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $shippingAddress->getCity(),
                'Name' => 'City',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
            [
                '_'    => $shippingAddress->getCountryId(),
                'Name' => 'Country',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ],
        ];

        if (!empty($streetFormat['house_number'])) {
            $shippingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'StreetNumber',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $shippingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'StreetNumberAdditional',
                'Group' => 'ShippingCustomer',
                'GroupID' => $GroupID,
            ];
        }

        return $shippingData;
    }

    /**
     * Failure message from failed Aferpay Transactions
     *
     * {@inheritdoc}
     */
    protected function getFailureMessageFromMethod($transactionResponse)
    {
        return $this->getFailureMessageFromMethodCommon($transactionResponse);
    }

    protected function getPriceFieldName()
    {
        return 'GrossUnitPriceIncl';
    }

    protected function formatPrice($price)
    {
        return number_format($price, 4, '.', '');
    }

    protected function formatShippingCostsLineVatPercentage($price)
    {
        return (int)$price;
    }

    protected function isAvailableBasedOnAmount(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && $quote->getId()) {
            $storeId = $quote->getStoreId();
            if ($this->helper->checkCustomerGroup('buckaroo_magento2_billink')) {
                $maximum = $this->getConfigData('max_amount_b2b', $storeId);
                $minimum = $this->getConfigData('min_amount_b2b', $storeId);
            } else {
                $maximum = $this->getConfigData('max_amount', $storeId);
                $minimum = $this->getConfigData('min_amount', $storeId);
            }

            /**
             * @var \Magento\Quote\Model\Quote $quote
             */
            $total = $quote->getGrandTotal();

            if ($total < 0.01) {
                return false;
            }

            if ($maximum !== null && $total > $maximum) {
                return false;
            }

            if ($minimum !== null && $total < $minimum) {
                return false;
            }

        }

        return true;
    }
}
