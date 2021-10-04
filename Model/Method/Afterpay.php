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

use Magento\Catalog\Model\Product\Type;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Quote\Model\Quote\AddressFactory;

class Afterpay extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_afterpay';

    /**
     * Max articles that can be handled by afterpay
     */
    const AFTERPAY_MAX_ARTICLE_COUNT = 99;

    /**
     * Business methods that will be used in afterpay.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'afterpay';

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
    protected $_canAuthorize            = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial       = true;

    // @codingStandardsIgnoreEnd

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
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
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
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay $afterpayConfig
         */
        $afterpayConfig = $this->configProviderMethodFactory->get($this->buckarooPaymentMethodCode);

        $methodName = $afterpayConfig->getPaymentMethodName();

        if ($payment->getAdditionalInformation('selectedBusiness')) {
            $methodName = $afterpayConfig->getPaymentMethodName($payment->getAdditionalInformation('selectedBusiness'));
        }

        return $methodName;
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
            'Version'          => 1,
            'RequestParameter' => $this->getAfterPayRequestParameters($payment),
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
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => 'CancelAuthorize',
            'Version'          => 1,
        ];

        $originalTrxKey = $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
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

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getAfterPayRequestParameters($payment)
    {
        // First data to set is the billing address data.
        $requestData = $this->getRequestBillingData($payment);

        $isDifferent = 'false';
        // If the shipping address is not the same as the billing it will be merged inside the data array.
        if ($this->isAddressDataDifferent($payment)) {
            $isDifferent = 'true';
            $requestData = array_merge($requestData, $this->getRequestShippingData($payment));
        }

        $requestData = array_merge(
            $requestData,
            [
                // Data variable to let afterpay know if the addresses are the same.
                [
                    '_'    => $isDifferent,
                    'Name' => 'AddressesDiffer'
                ]
            ]
        );

        if ($payment->getOrder()->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
            $this->updateShippingAddressByDpdParcel($quote, $requestData);
        }

        $this->handleShippingAddressByMyParcel($payment, $requestData);

        // Merge the customer data; ip, iban and terms condition.
        $requestData = array_merge($requestData, $this->getRequestCustomerData($payment));
        // Merge the business data
        $requestData = array_merge($requestData, $this->getRequestBusinessData($payment));
        // Merge the article data; products and fee's
        $requestData = $this->getRequestArticlesData($requestData, $payment);

        return $requestData;
    }

    public function updateShippingAddressByDpdParcel($quote, &$requestData)
    {
        $fullStreet = $quote->getDpdStreet();
        $matches = false;
        if ($fullStreet && preg_match('/(.*)\s([0-9]+)([^\w]*)([\w]*)/', $fullStreet, $matches)) {
            $street = $matches[1];
            $streetHouseNumber = $matches[2];
            $streetHouseNumberSuffix = @$matches[4];

            $mapping = [
                ['ShippingStreet', $street],
                ['ShippingPostalCode', $quote->getDpdZipcode()],
                ['ShippingCity', $quote->getDpdCity()],
                ['ShippingCountryCode', $quote->getDpdCountry()],
                ['ShippingHouseNumber', $streetHouseNumber],
                ['ShippingHouseNumberSuffix', $streetHouseNumberSuffix],
            ];

            $this->updateShippingAddressCommonMappingV2($mapping, $requestData);

            if (!$streetHouseNumberSuffix) {
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Name'] == 'ShippingHouseNumberSuffix') {
                        unset($requestData[$key]);
                    }
                }
            }
        }
    }

    /**
     * Get request Business data
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getRequestBusinessData($payment)
    {
        if ($payment->getAdditionalInformation('selectedBusiness') == self::BUSINESS_METHOD_B2B) {
            $requestData = [
                [
                    '_'    => 'true',
                    'Name' => 'B2B'
                ],
                [
                    '_'    => $payment->getAdditionalInformation('COCNumber'),
                    'Name' => 'CompanyCOCRegistration'
                ],
                [
                    '_'    => $payment->getAdditionalInformation('CompanyName'),
                    'Name' => 'CompanyName'
                ],
            ];
        } else {
            $requestData = [
                [
                    '_'    => 'false',
                    'Name' => 'B2B'
                ]
            ];
        }

        return $requestData;
    }

    /**
     * @param $requestData
     * @param $payment
     *
     * @return array
     */
    public function getRequestArticlesData($requestData, $payment)
    {
        if ($this->payRemainder) {
            return array_merge($requestData, $this->getRequestArticlesDataPayRemainder($payment));
        }

        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
        $cartData = $quote->getAllItems();

        // Set loop variables
        $articles = $requestData;
        $count    = 1;

        foreach ($cartData as $item) {
            // Child objects of configurable products should not be requested because afterpay will fail on unit prices.
            if (empty($item)
                || $this->calculateProductPrice($item, $includesTax) == 0
                || $item->getProductType() == Type::TYPE_BUNDLE
            ) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getProductId(),
                intval($item->getQty()),
                $this->calculateProductPrice($item, $includesTax),
                $this->getTaxCategory($payment->getOrder())
            );

            /*
             * @todo: Find better way to make taxClassId available by invoice and creditmemo creating for Afterpay
             */
            $payment->setAdditionalInformation('tax_pid_' . $item->getProductId(), $item->getTaxClassId());

            $articles = array_merge($articles, $article);

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder());

        if (!empty($serviceLine)) {
            $requestData = array_merge($articles, $serviceLine);
            $count++;
        } else {
            $requestData = $articles;
        }

        // Add additional shipping costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder());

        if (!empty($shippingCosts)) {
            $requestData = array_merge($requestData, $shippingCosts);
        }

        $discountline = $this->getDiscountLine($count, $payment);

        if (!empty($discountline)) {
            $requestData = array_merge($requestData, $discountline);
            $count++;
        }

        $quote = $this->helper->getQuote();
        $thirdPartyGiftCardLine = $this->getThirdPartyGiftCardLine($count, $quote);

        if (!empty($thirdPartyGiftCardLine)) {
            $requestData = array_merge($requestData, $thirdPartyGiftCardLine);
        }

        return $requestData;
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return array
     */
    public function getInvoiceArticleData($invoice)
    {
        $includesTax = $this->_scopeConfig->getValue(
            static::TAX_CALCULATION_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        // Set loop variables
        $articles = array();
        $count    = 1;

        foreach ($invoice->getAllItems() as $item) {
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getProductId(),
                intval($item->getQty()),
                $this->calculateProductPrice($item, $includesTax),
                $this->getTaxCategory($invoice->getOrder())
            );

            $articles = array_merge($articles, $article);

            // Capture calculates discount per order line
            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    $count,
                    'Korting op '. $item->getName(),
                    $item->getProductId(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    $this->getTaxCategory($invoice->getOrder())
                );
                $articles = array_merge($articles, $article);
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
     */
    public function getCreditmemoArticleData($payment)
    {
        if ($this->payRemainder) {
            return $this->getCreditmemoArticleDataPayRemainder($payment, false);
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
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getName(),
                $item->getProductId(),
                intval($item->getQty()),
                $this->calculateProductPrice($item, $includesTax) - round($item->getDiscountAmount() / $item->getQty(), 2),
                $this->getTaxCategory($payment->getOrder())
            );

            $itemsTotalAmount += $item->getQty() * ($this->calculateProductPrice($item, $includesTax) - $item->getDiscountAmount());

            $articles = array_merge($articles, $article);

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
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
            $count++;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($creditmemo, $count, $itemsTotalAmount);
        $articles = array_merge($articles, $shippingCosts);

        //Add diff line
        if($creditmemo->getBaseGrandTotal() != $itemsTotalAmount){
            $diff = $creditmemo->getBaseGrandTotal() - $itemsTotalAmount;
            $diffLine = $this->getDiffLine($count, $diff);
            $articles = array_merge($articles, $diffLine);
        }

        return $articles;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @return array
     */
    protected function getShippingCostsLine($order, $count = 0, &$itemsTotalAmount = 0)
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $shippingCostsArticle = [
            [
                '_'       => $shippingAmount,
                'Name'    => 'ShippingCosts',
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
            4
        );

        return $article;
    }

    /**
     * Get the third party gift card lines
     *
     * @param (int)                        $latestKey
     * @param  \Magento\Quote\Model\Quote $quote
     *
     * @return array
     */
    public function getThirdPartyGiftCardLine($latestKey, $quote)
    {
        $giftCardTotal = 0;
        $supportedGiftCards = ['amasty_giftcard', 'mageworx_giftcards'];
        $cartTotals = $quote->getTotals();

        foreach ($supportedGiftCards as $key => $giftCardCode) {
            if (!array_key_exists($giftCardCode, $cartTotals)) {
                continue;
            }

            if (is_array($cartTotals) || isset($cartTotals[$giftCardCode])) {
                $total = $cartTotals[$giftCardCode];
                $amount = $total->getValue();
                if ($amount !== 0) {
                    $giftCardTotal += $amount;
                }
            }
        }

        if ($giftCardTotal >= 0) {
            return [];
        }

        return $this->getArticleArrayLine(
            $latestKey,
            'Betaald bedrag',
            'BETAALD',
            1,
            round($giftCardTotal, 2),
            4
        );
    }

    /**
     * @param $latestKey
     * @param $articleDescription
     * @param $articleId
     * @param $articleQuantity
     * @param $articleUnitPrice
     * @param $articleVatCategory
     *
     * @return array
     */
    public function getArticleArrayLine(
        $latestKey,
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVatCategory = ''
    ) {
        $article = [
            [
                '_'       => $articleDescription,
                'Name'    => 'ArticleDescription',
                'GroupID' => $latestKey
            ],
            [
                '_'       => $articleId,
                'Name'    => 'ArticleId',
                'GroupID' => $latestKey
            ],
            [
                '_'       => $articleQuantity,
                'Name'    => 'ArticleQuantity',
                'GroupID' => $latestKey
            ],
            [
                '_'       => $articleUnitPrice,
                'Name'    => 'ArticleUnitPrice',
                'GroupID' => $latestKey
            ],
            [
                '_'       => $articleVatCategory,
                'Name'    => 'ArticleVatCategory',
                'GroupID' => $latestKey
            ]
        ];

        return $article;
    }

    protected function getTaxCategory($order)
    {
        $storeId = (int) $order->getStoreId();
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($storeId);

        $taxCategory = 4;

        if (!$taxClassId) {
            return $taxCategory;
        }
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay $afterPayConfig
         */
        $afterPayConfig = $this->configProviderMethodFactory
            ->get($this->_code);

        $highClasses   = explode(',', $afterPayConfig->getHighTaxClasses($storeId));
        $middleClasses = explode(',', $afterPayConfig->getMiddleTaxClasses($storeId));
        $lowClasses    = explode(',', $afterPayConfig->getLowTaxClasses($storeId));
        $zeroClasses   = explode(',', $afterPayConfig->getZeroTaxClasses($storeId));

        if (in_array($taxClassId, $highClasses)) {
            $taxCategory = 1;
        } elseif (in_array($taxClassId, $middleClasses)) {
            $taxCategory = 5;
        } elseif (in_array($taxClassId, $lowClasses)) {
            $taxCategory = 2;
        } elseif (in_array($taxClassId, $zeroClasses)) {
            $taxCategory = 3;
        } else {
            // No classes == 4
            $taxCategory = 4;
        }

        return $taxCategory;
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
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $streetFormat   = $this->formatStreet($billingAddress->getStreet());

        $birthDayStamp = str_replace('/', '-', $payment->getAdditionalInformation('customer_DoB'));
        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);

        if (
            ($this->buckarooPaymentMethodCode  === 'afterpay')
            &&
            $payment->getAdditionalInformation('selectedBusiness')
            &&
            ($payment->getAdditionalInformation('selectedBusiness') == 2)
            &&
            !$birthDayStamp
        ) {
            $birthDayStamp = '11-11-1990';
        }

        $billingData = [
            [
                '_'    => $billingAddress->getFirstname(),
                'Name' => 'BillingTitle',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'BillingGender',
            ],
            [
                '_'    => strtoupper(substr($billingAddress->getFirstname(), 0, 1)),
                'Name' => 'BillingInitials',
            ],
            [
                '_'    => $billingAddress->getLastName(),
                'Name' => 'BillingLastName',
            ],
            [
                '_'    => $birthDayStamp,
                'Name' => 'BillingBirthDate',
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'BillingStreet',
            ],
            [
                '_'    => $billingAddress->getPostcode(),
                'Name' => 'BillingPostalCode',
            ],
            [
                '_'    => $billingAddress->getCity(),
                'Name' => 'BillingCity',
            ],
            [
                '_'    => $billingAddress->getCountryId(),
                'Name' => 'BillingCountry',
            ],
            [
                '_'    => $billingAddress->getEmail(),
                'Name' => 'BillingEmail',
            ],
            [
                '_'    => $telephone,
                'Name' => 'BillingPhoneNumber',
            ],
            [
                '_'    => $billingAddress->getCountryId(),
                'Name' => 'BillingLanguage',
            ],
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

        $this->getRequestBillingDataExtra($billingAddress, $billingData);

        return $billingData;
    }

    protected function getRequestBillingDataExtra(\Magento\Sales\Api\Data\OrderAddressInterface $billingAddress, array &$billingData)
    {
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestShippingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddress
         */
        $shippingAddress = $payment->getOrder()->getShippingAddress();
        $streetFormat    = $this->formatStreet($shippingAddress->getStreet());

        $birthDayStamp = str_replace('/', '-', $payment->getAdditionalInformation('customer_DoB'));

        if (
            ($this->buckarooPaymentMethodCode  === 'afterpay')
            &&
            $payment->getAdditionalInformation('selectedBusiness')
            &&
            ($payment->getAdditionalInformation('selectedBusiness') == 2)
            &&
            !$birthDayStamp
        ) {
            $birthDayStamp = '11-11-1990';
        }

        $shippingData = [
            [
                '_'    => $shippingAddress->getFirstname(),
                'Name' => 'ShippingTitle',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'ShippingGender',
            ],
            [
                '_'    => strtoupper(substr($shippingAddress->getFirstname(), 0, 1)),
                'Name' => 'ShippingInitials',
            ],
            [
                '_'    => $shippingAddress->getLastName(),
                'Name' => 'ShippingLastName',
            ],
            [
                '_'    => $birthDayStamp,
                'Name' => 'ShippingBirthDate',
            ],
            [
                '_'    => $streetFormat['street'],
                'Name' => 'ShippingStreet',
            ],
            [
                '_'    => $shippingAddress->getPostcode(),
                'Name' => 'ShippingPostalCode',
            ],
            [
                '_'    => $shippingAddress->getCity(),
                'Name' => 'ShippingCity',
            ],
            [
                '_'    => $shippingAddress->getCountryId(),
                'Name' => 'ShippingCountryCode',
            ],
            [
                '_'    => $shippingAddress->getEmail(),
                'Name' => 'ShippingEmail',
            ],
            [
                '_'    => $shippingAddress->getCountryId(),
                'Name' => 'ShippingLanguage',
            ],
        ];

        $shippingAddressTelephone = $shippingAddress->getTelephone();
        $shippingPhoneNumber = empty($shippingAddressTelephone) ?
            $payment->getAdditionalInformation('customer_telephone') :
            $shippingAddressTelephone;
        if (empty($shippingPhoneNumber)) {
            $billingAddress = $payment->getOrder()->getBillingAddress();
            $shippingPhoneNumber = $billingAddress->getTelephone();
        }

        if (!empty($shippingPhoneNumber)) {
            $shippingData[] = [
                '_'    => $shippingPhoneNumber,
                'Name' => 'ShippingPhoneNumber',
            ];
        }

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
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestCustomerData($payment)
    {
        $accept = 'false';
        if ($payment->getAdditionalInformation('termsCondition')) {
            $accept = 'true';
        }

        $customerData = [
            [
                '_'    => $this->getRemoteAddress(),
                'Name' => 'CustomerIPAddress',
            ],
            [
                '_'    => $accept,
                'Name' => 'Accept',
            ]
        ];

        // Only required if afterpay paymentmethod is acceptgiro.
        if ($payment->getAdditionalInformation('customer_iban')) {
            $accountNumber = [
                [
                    '_'    => $payment->getAdditionalInformation('customer_iban'),
                    'Name' => 'CustomerAccountNumber',
                ]
            ];

            $customerData = array_merge($customerData, $accountNumber);
        }

        return $customerData;
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

        if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['street']       = trim($matches[3]);
            } else {
                $format['street']          = trim($matches[1]);
                $format['house_number']    = trim($matches[2]);
                $format['number_addition'] = trim($matches[3]);
            }
        }

        return $format;
    }

    /**
     * Failure message from failed Aferpay Transactions
     *
     * {@inheritdoc}
     */
    protected function getFailureMessageFromMethod($transactionResponse)
    {
        $transactionType = $transactionResponse->TransactionType;
        $methodMessage = '';

        if ($transactionType != 'C011' && $transactionType != 'C016') {
            return $methodMessage;
        }

        $subcodeMessage = $transactionResponse->Status->SubCode->_;
        $subcodeMessage = explode(':', $subcodeMessage);

        if (count($subcodeMessage) > 1) {
            array_shift($subcodeMessage);
        }

        $methodMessage = trim(implode(':', $subcodeMessage));

        return $methodMessage;
    }

    protected function getCaptureTransactionBuilderVersion()
    {
        return 1;
    }
}
