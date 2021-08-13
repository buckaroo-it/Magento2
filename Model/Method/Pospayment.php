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
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Registry\BuckarooRegistry as BuckarooRegistry;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Pospayment as PospaymentConfig;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\HTTP\Header;

class Pospayment extends AbstractMethod
{
    /** Payment Code*/
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_pospayment';

    /** @var string */
    public $buckarooPaymentMethodCode = 'pospayment';

    // @codingStandardsIgnoreStart
    /** @var string */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /** @var bool */
    protected $_canRefund               = false;

    /** @var bool */
    protected $_canVoid                 = false;

    /** @var bool */
    protected $_canRefundInvoicePartial = false;
    // @codingStandardsIgnoreEnd

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        BuckarooRegistry $buckarooRegistry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Developer\Helper\Data $developmentHelper,
        \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters $serviceParameters,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        Config $taxConfig,
        Calculation $taxCalculation,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        BuckarooLog $buckarooLog,
        SoftwareData $softwareData,
        AddressFactory $addressFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway = null,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory = null,
        \Buckaroo\Magento2\Helper\Data $helper = null,
        \Buckaroo\Magento2\Helper\PaymentGroupTransaction $paymentGroupTransactionHelper,
        \Magento\Framework\App\RequestInterface $request = null,
        \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
        \Magento\Framework\Pricing\Helper\Data $priceHelper = null,
        \Magento\Framework\HTTP\Client\Curl $curl,
        Header $header,
        CookieManagerInterface $cookieManager,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $buckarooRegistry,
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
            $resource,
            $resourceCollection,
            $gateway,
            $transactionBuilderFactory,
            $validatorFactory,
            $helper,
            $paymentGroupTransactionHelper,
            $request,
            $refundFieldsFactory,
            $configProviderFactory,
            $configProviderMethodFactory,
            $priceHelper,
            $curl,
            $data
        );

        $this->header = $header;
        $this->cookieManager = $cookieManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'pospayment',
            'Action'           => 'Pay',
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $this->getPosPaymentTerminalId(),
                    'Name' => 'TerminalID',
                ],
            ],
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
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * @return false|string
     */
    private function getPosPaymentTerminalId()
    {
         $terminalId = $this->cookieManager->getCookie('Pos-Terminal-Id');
        $this->logger2->addDebug(__METHOD__.'|1|');
        $this->logger2->addDebug(var_export($terminalId, true));
        return $terminalId;
    }

    /**
     * Check whether payment method can be used
     *
     * @param  \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (parent::isAvailable($quote)) {
            if (!$this->getPosPaymentTerminalId()) {
                return false;
            }

            $userAgent = $this->header->getHttpUserAgent();
            $userAgentConfiguration = trim($this->getConfigData('user_agent'));

            $this->logger2->addDebug(var_export([$userAgent, $userAgentConfiguration], true));

            if (strlen($userAgentConfiguration) > 0 && $userAgent != $userAgentConfiguration) {
                return false;
            }

            return true;
        }

        return false;

    }

    public function getOtherPaymentMethods()
    {
        return $this->getConfigData('other_payment_methods');
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
