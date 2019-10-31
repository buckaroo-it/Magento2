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
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;

class Afterpay extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_afterpay';

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
        \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
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
     * @throws \TIG\Buckaroo\Exception
     */
    public function getPaymentMethodName($payment)
    {
        /**
         * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Afterpay $afterpayConfig
         */
        $afterpayConfig = $this->configProviderMethodFactory->get('afterpay');

        $methodName = $afterpayConfig->getPaymentMethodName();

        if ($payment->getAdditionalInformation('selectedBusiness')) {
            $methodName = $afterpayConfig->getPaymentMethodName($payment->getAdditionalInformation('selectedBusiness'));
        }

        return $methodName;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $services = [
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => 'Pay',
            'Version'          => 1,
            'RequestParameter' =>
                $this->getAfterPayRequestParameters($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

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

        $capturePartial = false;

        $order = $payment->getOrder();
        $order_id = $order->getId();

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
        } else {
            //partial capture
            $capturePartial = true;
        }

        $services = [
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => 'Capture',
            'Version'          => 1
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
        $shippingCosts = $this->getShippingCostsLine($currentInvoice);
        $articles = array_merge($articles, $shippingCosts);

        $services['RequestParameter'] = $articles;


        /**
         * @noinspection PhpUndefinedMethodInspection
         */
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
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
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
            'Name'             => $this->getPaymentMethodName($payment),
            'Action'           => 'Authorize',
            'Version'          => 1,
            'RequestParameter' =>
                $this->getAfterPayRequestParameters($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

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
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'    => $this->getPaymentMethodName($payment),
            'Action'  => 'Refund',
            'Version' => 1,
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

        // Merge the customer data; ip, iban and terms condition.
        $requestData = array_merge($requestData, $this->getRequestCustomerData($payment));
        // Merge the business data
        $requestData = array_merge($requestData, $this->getRequestBusinessData($payment));
        // Merge the article data; products and fee's
        $requestData = $this->getRequestArticlesData($requestData, $payment);

        return $requestData;
    }

    /**
     * Get request Business data
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
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
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        /**
         * @var \Magento\Eav\Model\Entity\Collection\AbstractCollection|array $cartData
         */
        $cartData = $this->objectManager->create('Magento\Checkout\Model\Cart')->getItems();

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
                $item->getQty() . ' x ' . $item->getName(),
                $item->getProductId(),
                1,
                $this->calculateProductPrice($item, $includesTax),
                $this->getTaxCategory($item->getTaxClassId(), $payment->getOrder()->getStore())
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

        $serviceLine = $this->getServiceCostLine($count, $payment->getOrder(), $includesTax);

        if (!empty($serviceLine)) {
            $requestData = array_merge($articles, $serviceLine);
            $count++;
        } else {
            $requestData = $articles;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder());

        if (!empty($shippingCosts)) {
            $requestData = array_merge($requestData, $shippingCosts);
        }

        $discountline = $this->getDiscountLine($count, $payment);

        if (!empty($discountline)) {
            $requestData = array_merge($requestData, $discountline);
            $count++;
        }

        $taxLine = $this->getTaxLine($count, $payment->getOrder());

        if (!empty($taxLine)) {
            $requestData = array_merge($requestData, $taxLine);
            $count++;
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
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        // Set loop variables
        $articles = array();
        $count    = 1;

        foreach ($invoice->getAllItems() as $item) {
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $itemTaxClassId = $invoice->getOrder()->getPayment()
                                                  ->getAdditionalInformation('tax_pid_' . $item->getProductId());

            $article = $this->getArticleArrayLine(
                $count,
                (int) $item->getQty() . ' x ' . $item->getName(),
                $item->getProductId(),
                1,
                $this->calculateProductPrice($item, $includesTax),
                $this->getTaxCategory($itemTaxClassId, $invoice->getOrder()->getStore())
            );

            $articles = array_merge($articles, $article);

            // Capture calculates discount per order line
            if ($item->getDiscountAmount() > 0) {
                $count++;
                $article = $this->getArticleArrayLine(
                    $count,
                    'Korting op '. (int) $item->getQty() . ' x ' . $item->getName(),
                    $item->getProductId(),
                    1,
                    number_format(($item->getDiscountAmount()*-1), 2),
                    $this->getTaxCategory($item->getTaxClassId(), $invoice->getOrder()->getStore())
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
     */
    public function getCreditmemoArticleData($payment)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        $articles = [];
        $count = 1;

        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $itemTaxClassId = $payment->getAdditionalInformation('tax_pid_' . $item->getProductId());

            $article = $this->getArticleArrayLine(
                $count,
                $item->getQty() . ' x ' . $item->getName(),
                $item->getProductId(),
                1,
                $this->calculateProductPrice($item, $includesTax) - $item->getDiscountAmount(),
                $this->getTaxCategory($itemTaxClassId, $payment->getOrder()->getStore())
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

        // hasCreditmemos only returns true by actually saved creditmemos.
        // The current creditmemo is still "in progress" and thus has yet to be saved.
        if (count($articles) > 0 && !$payment->getOrder()->hasCreditmemos()) {
            $serviceLine = $this->getServiceCostLine($count, $creditmemo, $includesTax);
            $articles = array_merge($articles, $serviceLine);
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($creditmemo);
        $articles = array_merge($articles, $shippingCosts);

        return $articles;
    }

    /**
     * @param                                      $lastestKey
     * @param \Magento\Payment\Model\Order\Invoice $invoice
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
        if ($includesTax) {
            $productPrice = $productItem->getRowTotalInclTax();
        } else {
            $productPrice = $productItem->getRowTotal();
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
     */
    public function getServiceCostLine($latestKey, $order, $includesTax)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $buckarooFee = $order->getBuckarooFee();

        if ($includesTax) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $buckarooFeeLine = $order->getBaseBuckarooFee() + $order->getBuckarooFeeTaxAmount();
        } else {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $buckarooFeeLine = $order->getBaseBuckarooFee();
        }

        $article = [];

        if (false !== $buckarooFee && (double)$buckarooFee > 0) {
            $storeId = (int) $order->getStoreId();

            $article = $this->getArticleArrayLine(
                $latestKey,
                'Servicekosten',
                1,
                1,
                round($buckarooFeeLine, 2),
                $this->getTaxCategory($this->configProviderBuckarooFee->getTaxClass($storeId), $storeId)
            );
        }

        return $article;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     *
     * @return array
     */
    private function getShippingCostsLine($order)
    {
        $shippingCostsArticle = [];

        if ($order->getShippingAmount() <= 0) {
            return $shippingCostsArticle;
        }

        $shippingIncludesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX);
        $shippingAmount = $order->getShippingAmount();

        if ($shippingIncludesTax) {
            $shippingAmount += $order->getShippingTaxAmount();
        }

        $shippingCostsArticle = [
            [
                '_'       => $shippingAmount,
                'Name'    => 'ShippingCosts',
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
            4
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
        $articleVatCategory
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

    /**
     * @param          $taxClassId
     * @param null|int $storeId
     *
     * @return int
     */
    public function getTaxCategory($taxClassId, $storeId = null)
    {
        $taxCategory = 4;

        if (!$taxClassId) {
            return $taxCategory;
        }
        /**
         * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Afterpay $afterPayConfig
         */
        $afterPayConfig = $this->configProviderMethodFactory
            ->get(\TIG\Buckaroo\Model\Method\Afterpay::PAYMENT_METHOD_CODE);

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

        return $billingData;
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
                '_'    => $shippingAddress->getTelephone(),
                'Name' => 'ShippingPhoneNumber',
            ],
            [
                '_'    => $shippingAddress->getCountryId(),
                'Name' => 'ShippingLanguage',
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
        $shippingAddress = $payment->getOrder()->getShippingAddress();

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
}
