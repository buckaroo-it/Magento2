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

use Magento\Sales\Model\Order\Payment;
use Magento\Framework\Phrase;
use Magento\Catalog\Model\Product\Type;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;

class Afterpay20 extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_afterpay20';

    /**
     * Max articles that can be handled by afterpay
     */
    const AFTERPAY_MAX_ARTICLE_COUNT = 99;

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    const AFTERPAY_PAYMENT_METHOD_NAME = 'afterpay';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'afterpay20';

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
        $this->assignDataCommon($data);
        return $this;
    }

    /**
     * Get text for Discount
     *
     * @return Phrase
     */
    public function getDiscount() : Phrase
    {
        return __('Discount');
    }

    /**
     * Get text for Discount on
     *
     * @return Phrase
     */
    public function getDiscountOn() :Phrase
    {
        return __('Discount on');
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
        return static::AFTERPAY_PAYMENT_METHOD_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment, $serviceAction = 'Pay')
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $serviceAction = $this->getPayRemainder($payment, $transactionBuilder, $serviceAction);

        $services = [
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => $serviceAction,
            'RequestParameter' => $this->getPaymentRequestParameters($payment),
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        if ($serviceAction != 'PayRemainder') {
            $payment->setAdditionalInformation('skip_push', 1);
        }

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        return $this->getOrderTransactionBuilder($payment, 'Authorize');
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
        if (!$this->payRemainder) {
            $this->getRefundTransactionBuilderPartialSupport($payment, $transactionBuilder);
        }
        return $transactionBuilder;
    }

    protected function getRefundTransactionBuilderServices($payment, &$services)
    {
        $this->getRefundTransactionBuilderServicesAdd($payment, $services);
    }

    protected function getRefundTransactionBuilderVersion()
    {
        return null;
    }

    protected function updateShippingAddressByMyParcel($myParcelLocation, &$requestData)
    {
        $this->updateShippingAddressByMyParcelV2($myParcelLocation, $requestData);
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

        if ($this->payRemainder) {
            return $this->getRequestArticlesDataPayRemainder($payment);
        }

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

            //Skip bundles which have dynamic pricing on (0=yes, 1=no) - the underlying simples are also in the quote
            if ($item->getProductType() == Type::TYPE_BUNDLE
                && $item->getProduct()->getCustomAttribute('price_type')
                && $item->getProduct()->getCustomAttribute('price_type')->getValue() == 0
            ) {
                $bundleProductQty = $item->getQty();
                continue;
            }

            if (($item->getProductType() == 'configurable') && ($item->getTaxPercent() === null)) {
                continue;
            }

            if (!$item->getParentItemId()) {
                $bundleProductQty = 0;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $bundleProductQty ? (int) ($bundleProductQty * $item->getQty()): $item->getQty(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getTaxPercent()
            );

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder(), $includesTax);

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
                    $this->getDiscountOn() . ' ' . (int) $item->getQty() . ' x ' . $item->getName(),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    0
                );
                // @codingStandardsIgnoreStart
                $articles = array_merge($articles, $article);
                // @codingStandardsIgnoreEnd
            }

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
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
        if ($this->payRemainder) {
            return $this->getCreditmemoArticleDataPayRemainder($payment);
        }

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
            $prodPriceWithoutDiscount = round($prodPrice - $item->getDiscountAmount() / $item->getQty(), 2);
            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getSku(),
                $item->getQty(),
                $prodPriceWithoutDiscount,
                $item->getOrderItem()->getTaxPercent()
            );

            $itemsTotalAmount += $item->getQty() * $prodPriceWithoutDiscount;

            // @codingStandardsIgnoreStart
            $articles = array_merge($articles, $article);
            // @codingStandardsIgnoreEnd

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        // hasCreditmemos returns since 2.2.6 true or false.
        // The current creditmemo is still "in progress" and thus has yet to be saved.
        $serviceLine = $this->getServiceCostLine($count, $creditmemo, $itemsTotalAmount);
        if ($serviceLine) {
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
        if (abs($creditmemo->getGrandTotal() - $itemsTotalAmount) > 0.01) {
            $diff = $creditmemo->getGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($count, $diff);
            $articles = array_merge($articles, $diffLine);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
        }

        return $articles;
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
            (string)$this->getDiscount(),
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
                '_'       => $articleUnitPrice,
                'Name'    => 'GrossUnitPrice',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ],
            [
                '_'       => $articleVat ?? 0,
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
        $this->validateHouseNumber($streetFormat['house_number'], $billingAddress->getCountryId());

        $birthDayStamp = str_replace('/', '-', (string)$payment->getAdditionalInformation('customer_DoB'));
        $identificationNumber = $payment->getAdditionalInformation('customer_identificationNumber');
        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);
        $category = 'Person';

        if (
            $this->isCustomerB2B($order->getStoreId()) &&
            $billingAddress->getCountryId() === 'NL' &&
            !$this->isCompanyEmpty($billingAddress->getCompany())
        ) {
            $category = 'Company';
        }

        $billingData = [
            [
                '_'    => $category,
                'Name' => 'Category',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getFirstname(),
                'Name' => 'FirstName',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getLastName(),
                'Name' => 'LastName',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'Street',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getPostcode(),
                'Name' => 'PostalCode',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getCity(),
                'Name' => 'City',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getCountryId(),
                'Name' => 'Country',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $billingAddress->getEmail(),
                'Name' => 'Email',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
        ];

        if (!empty($telephone)) {
            $billingData[] = [
                '_'    => $telephone,
                'Name' => 'MobilePhone',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
            $billingData[] = [
                '_'    => $telephone,
                'Name' => 'Phone',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (!empty($streetFormat['house_number'])) {
            $billingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'StreetNumber',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'StreetNumberAdditional',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if ($billingAddress->getCountryId() == 'FI') {
            $billingData[] = [
                '_'    => $identificationNumber,
                'Name' => 'IdentificationNumber',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (
            (
                $billingAddress->getCountryId() == 'NL' &&
                $this->isCustomerB2C($billingAddress->getCompany(), $order->getStoreId())
            ) ||
            $billingAddress->getCountryId() == 'BE'
        ) {
            $billingData[] = [
                '_'    => $birthDayStamp,
                'Name' => 'BirthDate',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (
            $this->isCustomerB2B($order->getStoreId()) &&
            $billingAddress->getCountryId() === 'NL' &&
            !$this->isCompanyEmpty($billingAddress->getCompany())
        ) {
            $billingData = array_merge($billingData,[
                [
                    '_'    => $billingAddress->getCompany(),
                    'Name' => 'CompanyName',
                    'Group' => 'BillingCustomer',
                    'GroupID' => '',
                ],
                [
                    '_'    => $payment->getAdditionalInformation('customer_coc'),
                    'Name' => 'IdentificationNumber',
                    'Group' => 'BillingCustomer',
                    'GroupID' => '',
                ]
            ]);
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

        $streetFormat    = $this->formatStreet($shippingAddress->getStreet());
        $this->validateHouseNumber($streetFormat['house_number'], $shippingAddress->getCountryId());
        $category = 'Person';

        if (
            $this->isCustomerB2B($order->getStoreId()) &&
            $shippingAddress->getCountryId() === 'NL' &&
            !$this->isCompanyEmpty($shippingAddress->getCompany())
        ) {
            $category = 'Company';
        }

        $shippingData = [
            [
                '_'    => $category,
                'Name' => 'Category',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $shippingAddress->getFirstname(),
                'Name' => 'FirstName',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $shippingAddress->getLastName(),
                'Name' => 'LastName',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'Street',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $shippingAddress->getPostcode(),
                'Name' => 'PostalCode',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $shippingAddress->getCity(),
                'Name' => 'City',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $shippingAddress->getCountryId(),
                'Name' => 'Country',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
        ];

        if (!empty($streetFormat['house_number'])) {
            $shippingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'StreetNumber',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $shippingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'StreetNumberAdditional',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }

        if (
            $this->isCustomerB2B($order->getStoreId()) &&
            $shippingAddress->getCountryId() === 'NL' &&
            !$this->isCompanyEmpty($shippingAddress->getCompany())
        ) {
            $shippingData = array_merge($shippingData,[
                [
                    '_'    => $shippingAddress->getCompany(),
                    'Name' => 'CompanyName',
                    'Group' => 'ShippingCustomer',
                    'GroupID' => '',
                ],
                [
                    '_'    => $payment->getAdditionalInformation('customer_coc'),
                    'Name' => 'IdentificationNumber',
                    'Group' => 'ShippingCustomer',
                    'GroupID' => '',
                ]
            ]);
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
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) &&  $this->isAvailableB2B($quote);
    }
    /**
     * Check to see if payment is available when b2b enabled
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return boolean
     */
    public function isAvailableB2B(\Magento\Quote\Api\Data\CartInterface $quote)
    {
        $storeId = $quote->getStoreId();
        $b2bMin = $this->getConfigData('min_amount_b2b', $storeId);
        $b2bMax = $this->getConfigData('max_amount_b2b', $storeId);
       /**
         * @var \Magento\Quote\Model\Quote $quote
         */
        $total = $quote->getGrandTotal();

        //skip if b2c
        if (!$this->isCustomerB2B()) {
            return true;
        }

        //b2b available ony to NL
        if (
            $quote->getBillingAddress()->getCountryId() !== 'NL' ||
            $quote->getShippingAddress()->getCountryId() !== 'NL'
            ) {
            return true;
        }

        if ($b2bMax !== null && $total > $b2bMax) {
            return false;
        }

        if ($b2bMin !== null && $total < $b2bMin) {
            return false;
        }

        return true;
    }
    public function isCustomerB2B($storeId = null)
    {
        return $this->getConfigData('customer_type', $storeId) !== AfterpayCustomerType::CUSTOMER_TYPE_B2C;
    }
    public function isOnlyCustomerB2B($storeId = null)
    {
        return $this->getConfigData('customer_type', $storeId) === AfterpayCustomerType::CUSTOMER_TYPE_B2B;
    }

    public function isCustomerB2C($company, $storeId = null)
    {
        $customerType = $this->getConfigData('customer_type', $storeId);

        return $customerType ===  AfterpayCustomerType::CUSTOMER_TYPE_B2C || 
        (
            $customerType !== AfterpayCustomerType::CUSTOMER_TYPE_B2B &&
            $this->isCompanyEmpty($company)
        );
    }
     /**
     * Validate that we received a company.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validateAdditionalData() {

        $paymentInfo = $this->getInfoInstance();

        $shippingCompany = null;

        if ($paymentInfo instanceof Payment) {
            $storeId = $paymentInfo->getOrder()->getStoreId();
            $billingCompany = $paymentInfo->getOrder()->getBillingAddress()->getCompany();
            $shippingAddress = $paymentInfo->getOrder()->getShippingAddress();
        } else {
            $storeId = $paymentInfo->getQuote() !== null? $paymentInfo->getQuote()->getStoreId(): null;
            $billingCompany = $paymentInfo->getQuote()->getBillingAddress()->getCompany();
            $shippingAddress = $paymentInfo->getQuote()->getShippingAddress();
        }

        if ($shippingAddress !== null) {
            $shippingCompany = $shippingAddress->getCompany();
        }

        if (
            $this->isOnlyCustomerB2B($storeId) && 
            (
                $this->isCompanyEmpty($billingCompany) &&
                $this->isCompanyEmpty($shippingCompany)
            )
        ) {
            throw new \LogicException(
                __('Company name is required for this payment method')
            );
        }
        return $this;
    }

    /**
     * Check if company is empty
     *
     * @param string $company
     *
     * @return boolean
     */
    public function isCompanyEmpty(string $company = null)
    {
        if (null === $company) {
            return true;
        }
        
        return strlen(trim($company)) === 0;
    }

    /**
     * @param $response
     *
     * @return string
     */
    protected function getFailureMessage($response) {
        if (empty($response[0])) {
            return parent::getFailureMessage($response);
        }
        $transactionResponse = $response[0];
        $responseCode        = $transactionResponse->Status->Code->Code;

        if (!isset($transactionResponse->Services->Service->ResponseParameter->_)) {
            return parent::getFailureMessage($response);
        }

        $message = $transactionResponse->Services->Service->ResponseParameter->_;
        if (
            $responseCode === 690 &&
            strpos($message, "deliveryCustomer.address.countryCode") !== false
        ) {
             return "Pay rejected: It is not allowed to specify another country for the invoice and delivery address for Afterpay transactions.";
        }
        return parent::getFailureMessage($response);
    }

    private function validateHouseNumber($street, $country)
    {
        if ($country !== "DE") {
            return;
        }
        
        if (!is_string($street) || empty(trim($street))) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'A valid address is required, cannot find street number'
                )
            );
        }
    }
}
