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

use Magento\Catalog\Model\Product\Type;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Quote\Model\Quote\AddressFactory;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;

class Afterpay20 extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_afterpay20';

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
     * @var Calculation
     */
    private $taxCalculation;

    /**
     * @var Config
     */
    private $taxConfig;

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

    /** @var \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee */
    protected $configProviderBuckarooFee;

    /** @var SoftwareData */
    private $softwareData;

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
     * @param \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee          $configProviderBuckarooFee
     * @param AddressFactory                                          $addressFactory
     * @param SoftwareData                                            $softwareData
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
     *
     * @throws \TIG\Buckaroo\Exception
     */
    public function __construct(
        Calculation $taxCalculation,
        Config $taxConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Developer\Helper\Data $developmentHelper,
        \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        AddressFactory $addressFactory,
        SoftwareData $softwareData,
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

        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->softwareData = $softwareData;
        $this->taxCalculation = $taxCalculation;
        $this->taxConfig = $taxConfig;
        $this->addressFactory  = $addressFactory;
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
            $this->getInfoInstance()->setAdditionalInformation('customer_billingName', $additionalData['customer_billingName']);
            $this->getInfoInstance()->setAdditionalInformation('customer_identificationNumber', $additionalData['customer_identificationNumber']);

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);

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
     * @return string
     */
    public function getPaymentMethodName()
    {
        return static::AFTERPAY_PAYMENT_METHOD_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'afterpay',
            'Action'           => 'Pay',
            'RequestParameter' => $this->getAfterPayRequestParameters($payment),
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
    public function getCaptureTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

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

                $currentInvoice = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        }

        $services = [
            'Name'   => $this->getPaymentMethodName(),
            'Action' => 'Capture',
        ];

        // always get articles from invoice
        $articles = '';
        if (isset($currentInvoice)) {
            $articles = $this->getInvoiceArticleData($currentInvoice);
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);
            $serviceLine = $this->getServiceCostLine((count($articles)/5)+1, $currentInvoice, $includesTax);
            $articles = array_merge($articles, $serviceLine);
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($currentInvoice, (count($articles) + 1));
        $articles = array_merge($articles, $shippingCosts);

        $services['RequestParameter'] = $articles;

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setAmount($currentInvoiceTotal)
            ->setMethod('TransactionRequest')
            ->setCurrency($this->payment->getOrder()->getOrderCurrencyCode())
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(
                    self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
                )
            );

        // Partial Capture Settings
        if ($capturePartial) {
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId() . '-' . $numberOfInvoices)
                ->setOriginalTransactionKey($payment->getParentTransactionId());
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
            'Name'             => $this->getPaymentMethodName(),
            'Action'           => 'Authorize',
            'RequestParameter' => $this->getAfterPayRequestParameters($payment),
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
            'Name'   => $this->getPaymentMethodName(),
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
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'   => $this->getPaymentMethodName(),
            'Action' => 'Refund',
        ];

        $requestParams = $this->addExtraFields($this->_code);
        $services = array_merge($services, $requestParams);

        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $articles = [];

        if ($this->canRefundPartialPerInvoice() && $creditmemo) {
            //AddCreditMemoArticles
            $articles = $this->getCreditmemoArticleData($payment);
        }

        if (isset($services['RequestParameter'])) {
            $articles = array_merge($services['RequestParameter'], $articles);
        }

        $services['RequestParameter'] = $articles;

        /** @noinspection PhpUndefinedMethodInspection */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            )
            ->setChannel('CallCenter');

        if ($this->canRefundPartialPerInvoice() && $creditmemo) {
            $invoice = $creditmemo->getInvoice();

            $transactionBuilder->setInvoiceId($invoice->getOrder()->getIncrementId())
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    public function getAfterPayRequestParameters($payment)
    {
        // First data to set is the billing address data.
        $requestData = $this->getRequestBillingData($payment);

        // If the shipping address is not the same as the billing it will be merged inside the data array.
        if ($this->isAddressDataDifferent($payment)) {
            $requestData = array_merge($requestData, $this->getRequestShippingData($payment));
        }

        // Merge the article data; products and fee's
        $requestData = array_merge($requestData, $this->getRequestArticlesData($payment));

        return $requestData;
    }

    /**
     * @param $payment
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    public function getRequestArticlesData($payment)
    {
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        /**
         * @var \Magento\Eav\Model\Entity\Collection\AbstractCollection|array $cartData
         */
        $cartData = $this->objectManager->create('Magento\Checkout\Model\Cart')->getItems();

        // Set loop variables
        $articles = [];
        $count    = 1;

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($cartData as $item) {
            // Child objects of configurable products should not be requested because afterpay will fail on unit prices.
            if (empty($item)
                || $item->getRowTotalInclTax() == 0
                || $item->getProductType() == Type::TYPE_BUNDLE
            ) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getQty() . ' x ' . $item->getName(),
                $item->getSku(),
                1,
                $this->calculateProductPrice($item, $includesTax),
                $item->getTaxPercent()
            );

            $articles = array_merge($articles, $article);

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
            $count++;
        }

        $taxLine = $this->getTaxLine($count, $payment->getOrder());

        if (!empty($taxLine)) {
            $articles = array_merge($articles, $taxLine);
            $count++;
        }

        return $articles;
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    public function getInvoiceArticleData($invoice)
    {
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

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
                (int) $item->getQty() . ' x ' . $item->getName(),
                $item->getSku(),
                1,
//                $item->getRowTotalInclTax(),
                $this->calculateProductPrice($item, $includesTax),
                $item->getOrderItem()->getTaxPercent()
            );

            $articles = array_merge($articles, $article);

            // Capture calculates discount per order line
            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    $count,
                    'Korting op ' . (int) $item->getQty() . ' x ' . $item->getName(),
                    $item->getSku(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    0
                );
                $articles = array_merge($articles, $article);
            }

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $taxLine = $this->getTaxLine($count, $invoice);

        if (!empty($taxLine)) {
            $articles = array_merge($articles, $taxLine);
            $count++;
        }

        $requestData = $articles;

        return $requestData;
    }

    /**
     * @param $payment
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    public function getCreditmemoArticleData($payment)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        $articles = [];
        $count = 1;

        $refundType = $this->getRefundType($count);
        $articles = array_merge($articles, $refundType);

        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            if (empty($item) || $item->getRowTotalInclTax() == 0) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $count,
                $item->getQty() . ' x ' . $item->getName(),
                $item->getSku(),
                1,
                $this->calculateProductPrice($item, $includesTax) - $item->getDiscountAmount(),
                $item->getOrderItem()->getTaxPercent()
            );

            $articles = array_merge($articles, $article);

            if ($count < self::AFTERPAY_MAX_ARTICLE_COUNT) {
                $count++;
                continue;
            }

            break;
        }

        $taxLine = $this->getTaxLine($count, $payment->getCreditmemo());

        if (!empty($taxLine)) {
            $articles = array_merge($articles, $taxLine);
            $count++;
        }

        // hasCreditmemos returns since 2.2.6 true or false.
        // The current creditmemo is still "in progress" and thus has yet to be saved.
        if (count($articles) > 0 && !$payment->getOrder()->hasCreditmemos()) {
            $serviceLine = $this->getServiceCostLine($count, $creditmemo, $includesTax);
            $articles = array_merge($articles, $serviceLine);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
            $count++;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($creditmemo, $count);
        if (!empty($shippingCosts)) {
            $articles = array_merge($articles, $shippingCosts);

            $refundType = $this->getRefundType($count);
            $articles = array_merge($articles, $refundType);
            $count++;
        }

        return $articles;
    }

    /**
     * @param                                      $lastestKey
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return array
     */
    public function getPartialRequestGrandTotal($lastestKey, $invoice)
    {
        $article = $this->getArticleArrayLine(
            $lastestKey,
            'Total',
            '0',
            1,
            number_format($invoice->getBaseGrandTotal(), 2),
            4
        );

        return $article;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $productItem
     * @param                                 $includesTax
     *
     * @return mixed
     */
    public function calculateProductPrice($productItem, $includesTax)
    {
        $productPrice = $productItem->getRowTotal();

        if ($includesTax) {
            $productPrice = $productItem->getRowTotalInclTax();
        }

        return $productPrice;
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
     * @throws \TIG\Buckaroo\Exception
     */
    public function getServiceCostLine($latestKey, $order, $includesTax)
    {
        $store = $order->getStore();
        $buckarooFeeLine = $order->getBaseBuckarooFee();

        if ($includesTax) {
            $buckarooFeeLine += $order->getBuckarooFeeTaxAmount();
        }

        $article = [];

        $request = $this->taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($store);
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));


        if (false !== $buckarooFeeLine && (double)$buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                $latestKey,
                'Servicekosten',
                1,
                1,
                round($buckarooFeeLine, 2),
                $percent
            );
        }

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
                '_'       => 'Shipping fee',
                'Name'    => 'Description',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => $shippingAmount,
                'Name'    => 'GrossUnitPrice',
                'Group'   => 'Article',
                'GroupID' =>  $count,
            ],
            [
                '_'       => $percent,
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
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
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
     * Get the tax line
     *
     * @param (int)                                               $latestKey
     * @param InvoiceInterface|OrderInterface|CreditmemoInterface $payment
     *
     * @return array
     */
    public function getTaxLine($latestKey, $payment)
    {
        $taxes = $this->getTaxes($payment);
        $article = [];

        if ($taxes > 0) {
            $article = $this->getArticleArrayLine(
                $latestKey,
                'BTW',
                2,
                1,
                number_format($taxes, 2),
                4
            );
        }

        return $article;
    }

    /**
     * @param InvoiceInterface|OrderInterface|CreditmemoInterface $order
     *
     * @return float|int|null
     */
    private function getTaxes($order)
    {
        $catalogIncludesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);
        $shippingIncludesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX);

        $taxes = 0;

        if (!$catalogIncludesTax) {
            $taxes += $order->getTaxAmount() - $order->getShippingTaxAmount();
        }

        if (!$shippingIncludesTax) {
            $taxes += $order->getShippingTaxAmount();
        }

        return $taxes;
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
        $articleVat
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
                '_'       => $articleVat,
                'Name'    => 'VatPercentage',
                'GroupID' => $latestKey,
                'Group' => 'Article',
            ]
        ];

        return $article;
    }

    public function getRefundType($count)
    {
        $article = [
            [
                '_'       => 'Refund',
                'Name'    => 'RefundType',
                'GroupID' => $count,
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

        $birthDayStamp = str_replace('/', '-', $payment->getAdditionalInformation('customer_DoB'));
        $identificationNumber = $payment->getAdditionalInformation('customer_identificationNumber');
        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);
        $category = 'Person';

        $gender = 'Mrs';

        if ($payment->getAdditionalInformation('customer_gender') === '1') {
            $gender = 'Mr';
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
                '_'    => $telephone,
                'Name' => 'MobilePhone',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $telephone,
                'Name' => 'Phone',
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

        if ($billingAddress->getCountryId() == 'NL' || $billingAddress->getCountryId() == 'BE') {
            $billingData[] = [
                '_'    => $gender,
                'Name' => 'Salutation',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];

            $billingData[] = [
                '_'    => $birthDayStamp,
                'Name' => 'BirthDate',
                'Group' => 'BillingCustomer',
                'GroupID' => '',
            ];
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
        $shippingAddress = $order->getShippingAddress();
        $postNLPakjeGemakAddress = $this->getPostNLPakjeGemakAddressInQuote($order->getQuoteId());

        if (!empty($postNLPakjeGemakAddress) && !empty($postNLPakjeGemakAddress->getData())) {
            $shippingAddress = $postNLPakjeGemakAddress;
        }

        $streetFormat    = $this->formatStreet($shippingAddress->getStreet());
        $category = 'Person';

        $gender = 'Mrs';
        if ($payment->getAdditionalInformation('customer_gender') == '1') {
            $gender = 'Mr';
        }

        $shippingData = [
            [
                '_'    => $category,
                'Name' => 'Category',
                'Group' => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'    => $gender,
                'Name' => 'Salutation',
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

        return $shippingData;
    }

    /**
     * Check if there is a "pakjegemak" address stored in the quote by this order.
     * Afterpay wants to receive the "pakjegemak" address instead of the customer shipping address.
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

        if ($transactionType != 'C011' && $transactionType != 'C016' && $transactionType != 'C039' && $transactionType != 'I038') {
            return $methodMessage;
        }

        if ($transactionType == 'I038') {
            if (
                isset($transactionResponse->Services->Service->ResponseParameter->Name)
                &&
                ($transactionResponse->Services->Service->ResponseParameter->Name === 'ErrorResponseMessage')
                &&
                isset($transactionResponse->Services->Service->ResponseParameter->_)
            )
            return $transactionResponse->Services->Service->ResponseParameter->_;
        }

        $subcodeMessage = $transactionResponse->Status->SubCode->_;
        $subcodeMessage = explode(':', $subcodeMessage);

        if (count($subcodeMessage) > 1) {
            array_shift($subcodeMessage);
        }

        $methodMessage = trim(implode(':', $subcodeMessage));

        return $methodMessage;
    }
}
