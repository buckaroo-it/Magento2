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

use Magento\Tax\Model\Config;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Model\Push;
use Magento\Tax\Model\Calculation;
use Magento\Payment\Model\InfoInterface;
use Buckaroo\Magento2\Plugin\Method\Klarna;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\Klarna\Klarnain;
use Buckaroo\Magento2\Observer\AddInTestModeMessage;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    const BUCKAROO_ALL_TRANSACTIONS             = 'buckaroo_all_transactions';
    const BUCKAROO_PAYMENT_IN_TRANSIT           = 'buckaroo_payment_in_transit';
    const PAYMENT_FROM                          = 'buckaroo_payment_from';
    const PAYMENT_ATTEMPTS_REACHED_MESSAGE      = 'buckaroo_payment_attempts_reached_message';
    /**
     * The regex used to validate the entered BIC number
     */
    const BIC_NUMBER_REGEX = '^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$^';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode;

    /**
     * @var \Buckaroo\Magento2\Gateway\GatewayInterface
     */
    protected $gateway;

    /**
     * @var array
     */
    protected $response;

    /**
     * @var \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory
     */
    protected $transactionBuilderFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ValidatorFactory
     */
    protected $validatorFactory;

    /**
     * @var \Buckaroo\Magento2\Helper\Data
     */
    public $helper;

    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    public $priceHelper;

    /**
     * @var OrderPaymentInterface|InfoInterface
     */
    public $payment;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Factory
     */
    public $configProviderFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var \Buckaroo\Magento2\Model\RefundFieldsFactory
     */
    public $refundFieldsFactory;

    /**
     * @var bool
     */
    public $closeOrderTransaction = true;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction = true;

    /**
     * @var bool
     */
    public $closeCaptureTransaction = true;

    /**
     * @var bool
     */
    public $closeRefundTransaction = true;

    /**
     * @var bool
     */
    public $closeCancelTransaction = true;

    /**
     * @var bool|string
     */
    public $orderPlaceRedirectUrl = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * @var bool
     */
    public $usesRedirect = true;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var string
     */
    protected $_infoBlockType = 'Buckaroo\Magento2\Block\Info';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Developer\Helper\Data
     */
    protected $developmentHelper;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var null
     */
    public $remoteAddress = null;

    protected $logger2;

    public static $requestOnVoid = true;

    /**
     * @var Calculation
     */
    protected $taxCalculation;

    /**
     * @var Config
     */
    protected $taxConfig;

    /** @var \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee */
    protected $configProviderBuckarooFee;

    /** @var SoftwareData */
    protected $softwareData;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    protected $payRemainder = 0;

    protected $_code;

    /**
     * @var EventManager
     */
    private $eventManager;

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
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \Buckaroo\Magento2\Gateway\GatewayInterface $gateway
     * @param \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory
     * @param \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory
     * @param \Buckaroo\Magento2\Helper\Data $helper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param array $data
     *
     * @param GroupTransaction $groupTransaction
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
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        /**
         * @todo : Remove usage of objectManager, better to use DI.
         */
        $this->objectManager               = $objectManager;
        $this->gateway                     = $gateway;
        $this->transactionBuilderFactory   = $transactionBuilderFactory;
        $this->validatorFactory            = $validatorFactory; //Move to gateway?
        $this->helper                      = $helper;
        $this->request                     = $request;
        $this->refundFieldsFactory         = $refundFieldsFactory;
        $this->configProviderFactory       = $configProviderFactory; //Account and Refund used
        $this->configProviderMethodFactory = $configProviderMethodFactory; //Load interface, inject childs via di?
        $this->priceHelper                 = $priceHelper;
        $this->developmentHelper           = $developmentHelper;
        $this->quoteFactory                = $quoteFactory;
        $this->taxConfig                   = $taxConfig;
        $this->taxCalculation              = $taxCalculation;
        $this->configProviderBuckarooFee   = $configProviderBuckarooFee;
        $this->softwareData                = $softwareData;
        $this->addressFactory              = $addressFactory;
        $this->logger2                     = $buckarooLog;
        $this->eventManager                = $eventManager;
        $this->gateway->setMode(
            $this->helper->getMode($this->buckarooPaymentMethodCode)
        );
    }

    /**
     * @return bool
     */
    public function canRefund()
    {
        if (!parent::canRefund()) {
            return false;
        }

        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Refund $refundConfig
         */
        $refundConfig = $this->configProviderFactory->get('refund');

        if ($refundConfig->getEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * @param \Magento\Framework\DataObject $data
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        if ($data instanceof \Magento\Framework\DataObject) {
            $additionalSkip = $data->getAdditionalData();
            $skipValidation = $data->getBuckarooSkipValidation();

            if ($skipValidation === null && isset($additionalSkip['buckaroo_skip_validation'])) {
                $skipValidation = $additionalSkip['buckaroo_skip_validation'];
            }

            if (isset($additionalSkip[self::PAYMENT_FROM])) {
                $this->getInfoInstance()->setAdditionalInformation(self::PAYMENT_FROM, $additionalSkip[self::PAYMENT_FROM]);
            }

            $this->getInfoInstance()->setAdditionalInformation('buckaroo_skip_validation', $skipValidation);
        }
        return $this;
    }

    /**
     * @param  \Magento\Framework\DataObject $data
     *
     * @return array
     */
    public function assignDataConvertToArray(\Magento\Framework\DataObject $data)
    {
        if (!is_array($data)) {
            $data = $data->convertToArray();
        }

        return $data;
    }

    protected function assignDataCommon(array $data)
    {
        $additionalData = $data['additional_data'];
        if (isset($data['additional_data']['customer_gender'])) {
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
        }

        if (isset($data['additional_data']['termsCondition'])) {
            $this->getInfoInstance()->setAdditionalInformation('termsCondition', $additionalData['termsCondition']);
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

            if (isset($data['additional_data']['customer_coc'])) {
                $this->getInfoInstance()
                    ->setAdditionalInformation('customer_coc', $data['additional_data']['customer_coc']);
            }
        }
    }

    protected function assignDataCommonV2(array $data)
    {
        if (isset($data['additional_data']['customer_gender'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation('customer_gender', $data['additional_data']['customer_gender']);
        }

        if (isset($data['additional_data']['customer_billingFirstName'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation(
                    'customer_billingFirstName',
                    $data['additional_data']['customer_billingFirstName']
                );
        }

        if (isset($data['additional_data']['customer_billingMiddleName'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation(
                    'customer_billingMiddleName',
                    $data['additional_data']['customer_billingMiddleName']
                );
        }

        if (isset($data['additional_data']['customer_billingLastName'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation(
                    'customer_billingLastName',
                    $data['additional_data']['customer_billingLastName']
                );
        }

        if (isset($data['additional_data']['customer_email'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation('customer_email', $data['additional_data']['customer_email']);
        }
    }

    /**
     * Check whether payment method can be used
     *
     * @param  \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (null == $quote) {
            return false;
        }
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');
        if ($accountConfig->getActive() == 0) {
            return false;
        }

        $areaCode = $this->_appState->getAreaCode();
        if ('adminhtml' === $areaCode
            && $this->getConfigData('available_in_backend') !== null
            && $this->getConfigData('available_in_backend') == 0
        ) {
            return false;
        }

        if (!$this->isAvailableBasedOnIp($accountConfig, $quote)) {
            return false;
        }

        if (!$this->isAvailableBasedOnAmount($quote)) {
            return false;
        }

        if (!$this->isAvailableBasedOnCurrency($quote)) {
            return false;
        }

        if( $this->isSpamLimitActive() && $this->isSpamLimitReached($this->getPaymentAttemptsStorage())) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
     * @param \Magento\Quote\Api\Data\CartInterface      $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnIp(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $methodValue = $this->getConfigData('limit_by_ip');
        if ($accountConfig->getLimitByIp() == 1 || $methodValue == 1) {
            $storeId   = $quote ? $quote->getStoreId() : null;
            $isAllowed = $this->developmentHelper->isDevAllowed($storeId);

            if (!$isAllowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the grand total exceeds the maximum allowed total.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnAmount(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $storeId = $quote->getStoreId();
        $maximum = $this->getConfigData('max_amount', $storeId);
        $minimum = $this->getConfigData('min_amount', $storeId);

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

        return true;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnCurrency(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $allowedCurrenciesRaw = $this->getConfigData('allowed_currencies');
        $allowedCurrencies    = explode(',', (string)$allowedCurrenciesRaw);

        $currentCurrency = $quote->getCurrency()->getQuoteCurrencyCode();

        return $allowedCurrenciesRaw === null || in_array($currentCurrency, $allowedCurrencies);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array                               $postData
     *
     * @return bool
     */
    public function canProcessPostData($payment, $postData)
    {
        return true;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array                               $postData
     */
    public function processCustomPostData($payment, $postData)
    {
        return;
    }

    /**
     * @param $responseData
     *
     * @return bool
     */
    public function canPushInvoice($responseData)
    {
        if ($this->getConfigData('payment_action') == 'authorize') {
            return false;
        }

        return true;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string                                     $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        return parent::getConfigData($field, $storeId);
    }

    /**
     * @param bool  $ipToLong
     * @param array $alternativeHeaders
     *
     * @return bool|int|mixed|null|\Zend\Stdlib\ParametersInterface
     */
    public function getRemoteAddress($ipToLong = false, $alternativeHeaders = [])
    {
        if ($this->remoteAddress === null) {
            foreach ($alternativeHeaders as $var) {
                if ($this->request->getServer($var, false)) {
                    $this->remoteAddress = $this->request->getServer($var);
                    break;
                }
            }

            if (!$this->remoteAddress) {
                $this->remoteAddress = $this->request->getServer('REMOTE_ADDR');
            }
        }

        if (!$this->remoteAddress) {
            return false;
        }

        return $ipToLong ? ip2long($this->remoteAddress) : $this->remoteAddress;
    }

    /**
     * @return string
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getTitle()
    {
        $title = $this->getConfigData('title');

        if (!$this->configProviderMethodFactory->has($this->buckarooPaymentMethodCode)) {
            return $title;
        }

        $paymentFee = trim($this->configProviderMethodFactory->get($this->buckarooPaymentMethodCode)->getPaymentFee());
        if (!$paymentFee || (float) $paymentFee < 0.01) {
            return $title;
        }

        if (strpos($paymentFee, '%') === false) {
            $title .= ' + ' . $this->priceHelper->currency(number_format($paymentFee, 2), true, false);
        } else {
            $title .= ' + ' . $paymentFee;
        }

        return $title;
    }

    /**
     * @return bool|string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->orderPlaceRedirectUrl;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param float                                                       $amount
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function order(InfoInterface $payment, $amount)
    {

        if (!$payment instanceof OrderPaymentInterface
            || !$payment instanceof InfoInterface
        ) {
            throw new \InvalidArgumentException(
                'Buckaroo requires the payment to be an instance of "\Magento\Sales\Api\Data\OrderPaymentInterface"' .
                ' and "\Magento\Payment\Model\InfoInterface".'
            );
        }

        parent::order($payment, $amount);

        $this->eventManager->dispatch('buckaroo_order_before', ['payment' => $payment]);
        $this->cancelPreviousPendingOrder($payment);
        $this->payment = $payment;

        $transactionBuilder = $this->getOrderTransactionBuilder($payment);

        if (!$transactionBuilder) {
            throw new \LogicException(
                'Order action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            return $this;
        }

        $transaction = $transactionBuilder->build();

        try {
            $response = $this->orderTransaction($transaction);
        } catch (LimitReachException $th) {
            $this->setMaxAttemptsFlags($payment, $th->getMessage());
           return $this;
        }

        $this->saveTransactionData($response[0], $payment, $this->closeOrderTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        if (!(isset($response[0]->RequiredAction->Type) && $response[0]->RequiredAction->Type === 'Redirect')) {
            $this->setPaymentInTransit($payment, false);
        }

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

        $this->eventManager->dispatch('buckaroo_order_after', ['order' => $order]);

        $this->afterOrder($payment, $response);

        return $this;
    }

    /**
     * Should be overwritten by the respective payment method class when it has a specific failure message.
     *
     * @param $transactionResponse
     *
     * @return string
     */
    protected function getFailureMessageFromMethod($transactionResponse)
    {
        return '';
    }

    protected function getFailureMessageFromMethodCommon($transactionResponse)
    {
        $transactionType = $transactionResponse->TransactionType ?? '';
        $methodMessage   = '';

        if ($transactionType != 'C011' && $transactionType != 'C016' && $transactionType != 'C039' && $transactionType != 'I038' && $transactionType != 'C074') {
            return $methodMessage;
        }

        if ($transactionType == 'I038') {
            if (
                isset($transactionResponse->Services->Service->ResponseParameter->Name)
                &&
                ($transactionResponse->Services->Service->ResponseParameter->Name === 'ErrorResponseMessage')
                &&
                isset($transactionResponse->Services->Service->ResponseParameter->_)
            ) {
                return $transactionResponse->Services->Service->ResponseParameter->_;
            }

        }

        $subcodeMessage = $transactionResponse->Status->SubCode->_;
        $subcodeMessage = explode(':', $subcodeMessage);

        if (count($subcodeMessage) > 1) {
            array_shift($subcodeMessage);
        }

        $methodMessage = trim(implode(':', $subcodeMessage));

        return $methodMessage;
    }

    /**
     * @param $response
     *
     * @return string
     */
    protected function getFailureMessage($response)
    {
        $message = 'Unfortunately the payment was unsuccessful. Please try again or choose a different payment method.';

        if (!isset($response[0]) || empty($response[0])) {
            return $message;
        }

        $transactionResponse = $response[0];
        $responseCode        = $transactionResponse->Status->Code->Code;
        $billingCountry      = $this->payment->getOrder()->getBillingAddress()->getCountryId();

        if($responseCode == 491) {
            $errorMessage =  $this->getFirstError($transactionResponse);
            if(strlen(trim($errorMessage)) > 0) {
                return $errorMessage;
            }
        }

        $method = null;
        if ($this->payment->getMethodInstance() && !empty($this->payment->getMethodInstance()->buckarooPaymentMethodCode)) {
            $method = $this->payment->getMethodInstance()->buckarooPaymentMethodCode;
        }
        if ($method == 'trustly') {
            $methodMessage = $this->getFailureMessageFromMethod($transactionResponse);
            $message       = strlen($methodMessage) > 0 ? $methodMessage : $message;
            return $message;
        }

        $allowedResponseCodes = [490, 690];

        if ($billingCountry == 'NL' && in_array($responseCode, $allowedResponseCodes)) {
            $methodMessage = $this->getFailureMessageFromMethod($transactionResponse);
            $message       = strlen($methodMessage) > 0 ? $methodMessage : $message;
        }

        if (
            isset($transactionResponse->Status->SubCode->_) &&
            is_string($transactionResponse->Status->SubCode->_) &&
            strlen(trim($transactionResponse->Status->SubCode->_)) > 0
        ) {
                $message = $transactionResponse->Status->SubCode->_;
        }

        $fraudMessage = $this->getFailureMessageOnFraud($transactionResponse);
        if ($fraudMessage !== null) {
            return $fraudMessage;
        }

        return $message;
    }

    /**
     * @param $transactionResponse
     * @param $errorType
     * @return bool
     */
    public function hasError($transactionResponse, $errorType): bool
    {
        return !empty($transactionResponse->RequestErrors) && !empty($transactionResponse->RequestErrors->$errorType);
    }

    /**
     * @param $transactionResponse
     * @return string
     */
    public function getFirstError($transactionResponse): string
    {
        $errorTypes = ['ChannelError', 'ServiceError', 'ActionError', 'ParameterError', 'CustomParameterError'];

        foreach ($errorTypes as $errorType) {
            if ($this->hasError($transactionResponse, $errorType)) {
                return $transactionResponse->RequestErrors->$errorType->_;

            }
        }

        return '';
    }

    public function getFailureMessageOnFraud($transactionResponse)
    {
        if (
        isset($transactionResponse->Status->SubCode->Code) &&
        $transactionResponse->Status->SubCode->Code == 'S103'
        ) {
            return __('An anti-fraud rule has blocked this transaction automatically. Please contact the webshop.');
        }
    }
    /**
     * @param \Buckaroo\Magento2\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function orderTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction)
    {
        $response = $this->gateway->authorize($transaction);

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'The transaction response could not be verified.'
                )
            );
        }

        if (!$this->validatorFactory->get('transaction_response_status')->validate($response)) {
            $this->updateRateLimiterCount();
            $failureMessage = $this->getFailureMessage($response);

            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase($failureMessage)
            );
        }

        return $response;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param float                                                       $amount
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        if (!$payment instanceof OrderPaymentInterface
            || !$payment instanceof InfoInterface
        ) {
            throw new \InvalidArgumentException(
                'Buckaroo requires the payment to be an instance of "\Magento\Sales\Api\Data\OrderPaymentInterface"' .
                ' and "\Magento\Payment\Model\InfoInterface".'
            );
        }

        parent::authorize($payment, $amount);

        $this->eventManager->dispatch('buckaroo_authorize_before', ['payment' => $payment]);

        $this->cancelPreviousPendingOrder($payment);
        $this->payment = $payment;

        $transactionBuilder = $this->getAuthorizeTransactionBuilder($payment);

        if (!$transactionBuilder) {
            throw new \LogicException(
                'Authorize action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            return $this;
        }

        $transaction = $transactionBuilder->build();

        try {
            $response = $this->authorizeTransaction($transaction);
        } catch (LimitReachException $th) {
            $this->setMaxAttemptsFlags($payment, $th->getMessage());
           return $this;
        }

        $this->saveTransactionData($response[0], $payment, $this->closeAuthorizeTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        if (!(isset($response[0]->RequiredAction->Type) && $response[0]->RequiredAction->Type === 'Redirect')) {
            $this->setPaymentInTransit($payment, false);
        }

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

        $this->eventManager->dispatch('buckaroo_authorize_after', ['order' => $order]);

        $this->afterAuthorize($payment, $response);

        return $this;
    }

    /**
     * @param \Buckaroo\Magento2\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function authorizeTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction)
    {
        $response = $this->gateway->authorize($transaction);

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'The transaction response could not be verified.'
                )
            );
        }

        if (!$this->validatorFactory->get('transaction_response_status')->validate($response)) {
            $this->updateRateLimiterCount();
            $failureMessage = $this->getFailureMessage($response);

            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase($failureMessage)
            );
        }

        return $response;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param float                                                       $amount
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function capture(InfoInterface $payment, $amount)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|' . var_export($amount, true));

        if (!$payment instanceof OrderPaymentInterface
            || !$payment instanceof InfoInterface
        ) {
            throw new \InvalidArgumentException(
                'Buckaroo requires the payment to be an instance of "\Magento\Sales\Api\Data\OrderPaymentInterface"' .
                ' and "\Magento\Payment\Model\InfoInterface".'
            );
        }

        $activeMode = $this->helper->getMode($this->buckarooPaymentMethodCode, $payment->getOrder()->getStore());

        if (!$activeMode) {
            $activeMode = 2;
        }

        parent::capture($payment, $amount);

        $this->payment = $payment;

        $transactionBuilder = $this->getCaptureTransactionBuilder($payment);

        if (!$transactionBuilder) {
            throw new \LogicException(
                'Capture action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            return $this;
        }

        $transaction = $transactionBuilder->build();

        $this->gateway->setMode($activeMode);

        $response = $this->captureTransaction($transaction);

        $this->saveTransactionData($response[0], $payment, $this->closeCaptureTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        $this->afterCapture($payment, $response);

        return $this;
    }

    /**
     * @param \Buckaroo\Magento2\Gateway\Http\Transaction $transaction
     *
     * @return array|\StdClass
     * @throws \Buckaroo\Magento2\Exception
     */
    public function captureTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');

        $response = $this->gateway->capture($transaction);

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'The transaction response could not be verified.'
                )
            );
        }

        if (!$this->validatorFactory->get('transaction_response_status')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'Unfortunately the payment was unsuccessful. Please try again or choose a different payment method.'
                )
            );
        }

        return $response;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param float                                                       $amount
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');

        if (!$payment instanceof OrderPaymentInterface
            || !$payment instanceof InfoInterface
        ) {
            throw new \InvalidArgumentException(
                'Buckaroo requires the payment to be an instance of "\Magento\Sales\Api\Data\OrderPaymentInterface"' .
                ' and "\Magento\Payment\Model\InfoInterface".'
            );
        }

        if (empty($amount)) {
            throw new \Exception('Giftcard cannot be refunded without order items');
        }

        $activeMode = $this->helper->getMode($this->buckarooPaymentMethodCode, $payment->getOrder()->getStore());
        if (!$activeMode) {
            $activeMode = 2;
        }
        $this->gateway->setMode($activeMode);

        parent::refund($payment, $amount);

        $this->logger2->addDebug(__METHOD__ . '|5|');

        $this->payment        = $payment;
        $paymentCm3InvoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (strlen((string)$paymentCm3InvoiceKey) > 0) {
            $this->createCreditNoteRequest($payment);
        }

        $amount = $this->refundGroupTransactions($payment, $amount);

        $this->logger2->addDebug(__METHOD__ . '|10|' . var_export($amount, true));

        if ($amount <= 0) {
            return $this;
        }

        $transactionBuilder = $this->getRefundTransactionBuilder($payment);

        if (!$transactionBuilder) {
            $this->logger2->addDebug(__METHOD__ . '|20|');
            throw new \LogicException(
                'Refund action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            $this->logger2->addDebug(__METHOD__ . '|25|');
            return $this;
        }
        $transactionBuilder->setAmount($amount);

        $transaction = $transactionBuilder->build();

        $response = $this->refundTransaction($transaction, $payment);

        $this->saveTransactionData($response[0], $payment, $this->closeRefundTransaction, false);
        $this->afterRefund($payment, $response);

        return $this;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return $this
     */
    public function createCreditNoteRequest($payment)
    {
        $paymentCm3InvoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (strlen($paymentCm3InvoiceKey) <= 0) {
            return $this;
        }

        $originalTransactionKey = $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        $this->void($payment);

        /** Void() saves the newly returned transaction key by default.
         * But we don't want to save the new key with CreateCreditNote requests,
         * therefore we reset and retain the original key.
         */
        $payment->setAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $originalTransactionKey);

        return $this;
    }

    /**
     * @param \Buckaroo\Magento2\Gateway\Http\Transaction $transaction
     *
     * @return array|\StdClass
     * @throws \Buckaroo\Magento2\Exception
     */
    public function refundTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction, $payment = null)
    {
        $response = $this->gateway->refund($transaction);

        $pendingApprovalStatus = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL');

        if (
            !empty($response[0]->Status->Code->Code)
            && ($response[0]->Status->Code->Code == $pendingApprovalStatus)
            && $payment
            && !empty($response[0]->RelatedTransactions->RelatedTransaction->_)
        ) {
            $this->logger2->addDebug(__METHOD__ . '|10|');
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            $buckarooTransactionKeysArray[$response[0]->RelatedTransactions->RelatedTransaction->_] =
                $response[0]->Status->Code->Code;
            $payment->setAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $buckarooTransactionKeysArray
            );
            $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $connection->rollBack();

            $payment->getOrder()->addStatusHistoryComment(
                __("The refund has been initiated but it is waiting for a approval. Login to the Buckaroo Plaza to finalize the refund by approving it.")
            )->setIsCustomerNotified(false)->save();

            $messageManager = $this->objectManager->get('Magento\Framework\Message\ManagerInterface');
            $messageManager->addError(
                __("Refund has been initiated, but it needs to be approved, so you need to wait for an approval")
            );
            $payment->save();
        }

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'The transaction response could not be verified.'
                )
            );
        }

        if (!$this->validatorFactory->get('transaction_response_status')->validate($response)) {
            $failureMessage = $this->getFailureMessage($response);

            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase($failureMessage)
            );
        }

        return $response;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function cancel(InfoInterface $payment)
    {
        parent::cancel($payment);
        return $this->void($payment);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return $this
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException|\InvalidArgumentException
     */
    public function void(InfoInterface $payment)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');
        if (!$payment instanceof OrderPaymentInterface
            || !$payment instanceof InfoInterface
        ) {
            throw new \InvalidArgumentException(
                'Buckaroo requires the payment to be an instance of "\Magento\Sales\Api\Data\OrderPaymentInterface"' .
                ' and "\Magento\Payment\Model\InfoInterface".'
            );
        }

        $activeMode = $this->helper->getMode($this->buckarooPaymentMethodCode, $payment->getOrder()->getStore());
        if (!$activeMode) {
            $activeMode = 2;
        }
        $this->gateway->setMode($activeMode);

        $this->_canVoid = true;
        parent::void($payment);

        $this->payment = $payment;

        // Do not cancel authorize when accept authorize is failed.
        // buckaroo_failed_authorize is set in Push.php
        if ($this->payment->getAdditionalInformation('buckaroo_failed_authorize') == 1) {
            $this->logger2->addDebug(__METHOD__ . '|5|');
            return $this;
        }

        if (self::$requestOnVoid) {
            $this->logger2->addDebug(__METHOD__ . '|10|');
            $transactionBuilder = $this->getVoidTransactionBuilder($payment);
        } else {
            $this->logger2->addDebug(__METHOD__ . '|15|');
            $transactionBuilder = true;
        }

        if (!$transactionBuilder) {
            $this->logger2->addDebug(__METHOD__ . '|20|');
            throw new \LogicException(
                'Void action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            $this->logger2->addDebug(__METHOD__ . '|25|');
            return $this;
        }

        $this->logger2->addDebug(__METHOD__ . '|30|');

        $transaction = $transactionBuilder->build();

        $response = $this->voidTransaction($transaction);

        $this->saveTransactionData($response[0], $payment, $this->closeCancelTransaction, true);

        $payment->setAdditionalInformation('voided_by_buckaroo', true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->addToRegistry('buckaroo_response', $response);

        $this->afterVoid($payment, $response);

        return $this;
    }

    /**
     * @param \Buckaroo\Magento2\Gateway\Http\Transaction $transaction
     *
     * @return array|\StdClass
     * @throws \Buckaroo\Magento2\Exception
     */
    public function voidTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction)
    {
        $response = $this->gateway->void($transaction);

        if (!$this->validatorFactory->get('transaction_response')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'The transaction response could not be verified.'
                )
            );
        }

        if (!$this->validatorFactory->get('transaction_response_status')->validate($response)) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'Unfortunately the payment authorization could not be voided. Please try again.'
                )
            );
        }

        return $response;
    }

    /**
     * @param string $key
     * @param        $value
     */
    private function addToRegistry($key, $value)
    {
        // if the key doesn't exist or is empty, the data can be directly added and registered
        if (!$this->_registry->registry($key)) {
            $this->_registry->register($key, [$value]);
            return;
        }

        $registryValue   = $this->_registry->registry($key);
        $registryValue[] = $value;

        $this->_registry->unregister($key);
        $this->_registry->register($key, $registryValue);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterOrder($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_order_after', $payment, $response);
    }

    /**
     * {@inheritdoc}
     */
    protected function afterOrderCommon($payment, $response)
    {
        if (empty($response[0]->Services->Service)) {
            return self::afterOrder($payment, $response);
        }

        $invoiceKey = '';
        $services   = $response[0]->Services->Service;

        if (!is_array($services)) {
            $services = [$services];
        }

        foreach ($services as $service) {
            if ($service->Name == 'CreditManagement3') {
                $invoiceKey = $this->getCM3InvoiceKey($service->ResponseParameter);
            }
        }

        if (strlen($invoiceKey) > 0) {
            $payment->setAdditionalInformation('buckaroo_cm3_invoice_key', $invoiceKey);
        }

        return self::afterOrder($payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterAuthorize($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_authorize_after', $payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterCapture($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_capture_after', $payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterRefund($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_refund_after', $payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterCancel($payment, $response)
    {
        return $this->afterVoid($payment, $response);
    }
    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterVoid($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_void_after', $payment, $response);
    }

    /**
     * @param $name
     * @param $payment
     * @param $response
     *
     * @return $this
     */
    protected function dispatchAfterEvent($name, $payment, $response)
    {
        $this->_eventManager->dispatch(
            $name,
            [
                'payment'  => $payment,
                'response' => $response,
            ]
        );

        return $this;
    }

    /**
     * @param \StdClass $response
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param                                                                                    $close
     * @param bool $saveId
     *
     * @return OrderPaymentInterface|InfoInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function saveTransactionData(
        \StdClass $response,
        InfoInterface $payment,
        $close,
        $saveId = false
    ) {
        if (!empty($response->Key)) {
            $transactionKey = $response->Key;
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $payment->setIsTransactionClosed($close);

            /**
             * Recursively convert object to array.
             */
            $arrayResponse = json_decode(json_encode($response), true);

            /**
             * Save the transaction's response as additional info for the transaction.
             */
            $rawInfo = $this->getTransactionAdditionalInfo($arrayResponse);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $payment->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                $rawInfo
            );

            $payment->getMethodInstance()->processCustomPostData($payment, $response);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $payment->setTransactionId($transactionKey);

            $this->setPaymentInTransit($payment);
            /**
             * Save the payment's transaction key.
             */
            if ($saveId) {
                $payment
                    ->setAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey)
                    ->setAdditionalInformation(
                        AddInTestModeMessage::PAYMENT_IN_TEST_MODE,
                        !empty($response->IsTest) && $response->IsTest === true
                    );
            }

            $skipFirstPush = $payment->getAdditionalInformation('skip_push');
            /**
             * Buckaroo Push is send before Response, for correct flow we skip the first push
             * for some payment methods
             * @todo when buckaroo changes the push / response order this can be removed
             */
            if ($skipFirstPush > 0) {
                $payment->setAdditionalInformation('skip_push', $skipFirstPush - 1);
                if (!empty($payment->getOrder()) && !empty($payment->getOrder()->getId())) {
                    // Only save payment if order is already saved, this to avoid foreign key constraint error
                    // on table sales_order_payment, column parent_id.
                    $payment->save();
                }
            }
        }

        return $payment;
    }
    /**
     * Set flag if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     *
     * @return void
     */
    public function setPaymentInTransit(OrderPaymentInterface $payment, $inTransit = true)
    {
        $payment->setAdditionalInformation(self::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array)
    {
        return $this->helper->getTransactionAdditionalInfo($array);
    }

    /**
     * @param string $paymentMethodCode
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function addExtraFields($paymentMethodCode)
    {
        $requestParams = $this->request->getParams();
        $services      = [];

        if (empty($requestParams['creditmemo'])) {
            return $services;
        }

        $creditMemoParams = $requestParams['creditmemo'];
        $extraFields      = $this->refundFieldsFactory->get($paymentMethodCode);

        if (empty($extraFields)) {
            return $services;
        }

        /**
         * If extra fields are found, attach these as 'RequestParameter' to the services.
         */
        foreach ($extraFields as $extraField) {
            $code                           = $extraField['code'];
            $services['RequestParameter'][] = [
                '_'    => "$creditMemoParams[$code]",
                'Name' => $code,
            ];
        }

        return $services;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    abstract public function getOrderTransactionBuilder($payment);

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    abstract public function getAuthorizeTransactionBuilder($payment);

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    public function getCaptureTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $capturePartial = true;

        $order = $payment->getOrder();

        $totalOrder          = $order->getBaseGrandTotal();
        $numberOfInvoices    = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }
                $this->logger2->addDebug(__METHOD__ . '|10|' . var_export($oInvoice->getGrandTotal(), true));
                $currentInvoice      = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getGrandTotal();
            }
        }

        if ($this->helper->areEqualAmounts($totalOrder, $currentInvoiceTotal) && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        }

        $services = [
            'Name'   => $this->getPaymentMethodName($payment),
            'Action' => $this->getCaptureTransactionBuilderAction(),
        ];
        if (!is_null($this->getCaptureTransactionBuilderVersion())) {
            $services['Version'] = $this->getCaptureTransactionBuilderVersion();
        }

        $services['RequestParameter'] = $this->getCaptureTransactionBuilderArticles($payment, $currentInvoice, $numberOfInvoices);

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

    protected function getCaptureTransactionBuilderAction()
    {
        return 'Capture';
    }

    protected function getCaptureTransactionBuilderVersion()
    {
        return null;
    }

    protected function getCaptureTransactionBuilderArticles($payment, $currentInvoice, $numberOfInvoices)
    {
        if (isset($currentInvoice)) {
            $articles = $this->getInvoiceArticleData($currentInvoice);
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $serviceLine = $this->getServiceCostLine((count($articles) / 5) + 1, $currentInvoice);
            $articles    = array_merge($articles, $serviceLine);
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($currentInvoice, (count($articles) + 1));
        $articles      = array_merge($articles, $shippingCosts);

        return $articles;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    public function getRefundTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'   => $this->getPaymentMethodName($payment),
            'Action' => 'Refund',
        ];

        if (!is_null($this->getRefundTransactionBuilderVersion())) {
            $services['Version'] = $this->getRefundTransactionBuilderVersion();
        }

        $requestParams = $this->addExtraFields($this->_code);
        $services      = array_merge($services, $requestParams);

        $this->getRefundTransactionBuilderServices($payment, $services);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            );

        if ($this->getRefundTransactionBuilderChannel()) {
            $transactionBuilder->setChannel($this->getRefundTransactionBuilderChannel());
        }

        return $transactionBuilder;
    }

    protected function getRefundTransactionBuilderServices($payment, &$services)
    {
    }

    protected function getRefundTransactionBuilderServicesAdd($payment, &$services)
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $articles   = [];

        if ($this->canRefundPartialPerInvoice() && $creditmemo) {
            //AddCreditMemoArticles
            $articles = $this->getCreditmemoArticleData($payment);
        }

        if (isset($services['RequestParameter'])) {
            $articles = array_merge($services['RequestParameter'], $articles);
        }

        $services['RequestParameter'] = $articles;
    }

    protected function getRefundTransactionBuilderPartialSupport($payment, $transactionBuilder)
    {
        $creditmemo = $payment->getCreditmemo();
        if ($this->canRefundPartialPerInvoice() && $creditmemo) {
            $invoice = $creditmemo->getInvoice();

            $transactionBuilder->setInvoiceId($invoice->getOrder()->getIncrementId())
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }
    }
    protected function getRefundTransactionBuilderVersion()
    {
        return 1;
    }

    protected function getRefundTransactionBuilderChannel()
    {
        return 'CallCenter';
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    abstract public function getVoidTransactionBuilder($payment);

    public function refundGroupTransactions(InfoInterface $payment, $amount)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');

        $order                   = $payment->getOrder();
        $totalOrder              = $order->getBaseGrandTotal();
        $paymentGroupTransaction = $this->objectManager->create('\Buckaroo\Magento2\Helper\PaymentGroupTransaction');

        $requestParams = $this->request->getParams();
        if (isset($requestParams['creditmemo']) && !empty($requestParams['creditmemo']['buckaroo_already_paid'])) {
            foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $transaction => $amount_value) {

                $transaction = explode('|', $transaction);
                $totalOrder  = $totalOrder - $transaction[2];

                $groupTransaction = $paymentGroupTransaction->getGroupTransactionByTrxId($transaction[0]);

                $this->logger2->addDebug(__METHOD__ . '|10|' . var_export([$amount_value, $amount], true));

                if ($amount_value > 0 && $amount > 0) {
                    if ($amount < $amount_value) {
                        $amount_value = $amount;
                    }
                    $amount = $amount - $amount_value;
                    $this->logger2->addDebug(__METHOD__ . '|15|' . var_export([$amount], true));
                    $transactionBuilder = $this->transactionBuilderFactory->get('refund');

                    $services = [
                        'Name'    => $transaction[1],
                        'Action'  => 'Refund',
                        'Version' => 1,
                    ];

                    $transactionBuilder->setOrder($payment->getOrder())
                        ->setServices($services)
                        ->setMethod('TransactionRequest')
                        ->setOriginalTransactionKey($transaction[0]);

                    $transactionBuilder->setAmount($amount_value);
                    $transaction = $transactionBuilder->build();
                    $response    = $this->refundTransaction($transaction);

                    $this->logger2->addDebug(__METHOD__ . '|16| ' . var_export($response, true));

                    $this->saveTransactionData($response[0], $payment, $this->closeRefundTransaction, false);


                    foreach ($groupTransaction as $item) {
                        $prevRefundAmount = $item->getData('refunded_amount');
                        $newRefundAmount = $amount_value;

                        if ($prevRefundAmount !== null) {
                            $newRefundAmount += $prevRefundAmount;
                        }
                        $item->setData('refunded_amount', $newRefundAmount);
                        $item->save();
                    }

                    $this->payRemainder = $amount;
                }
            }
        }

        $this->logger2->addDebug(__METHOD__ . '|20|' . var_export([$amount, $totalOrder, $amount >= 0.01], true));

        if ($amount >= 0.01) {
            $groupTransactionAmount = $paymentGroupTransaction->getGroupTransactionAmount($order->getIncrementId());
            if (
                ($groupTransactionAmount > 0.01)
                &&
                empty($requestParams['creditmemo']['buckaroo_already_paid'])
                &&
                !empty($requestParams['creditmemo']['adjustment_negative'])
            ) {
                $this->logger2->addDebug(__METHOD__ . '|22|');
                $payment->getOrder()->setAdjustmentNegative(0);
            }
            if ($amount == $order->getBaseGrandTotal() && $groupTransactionAmount > 0) {
                $this->logger2->addDebug(__METHOD__ . '|25|' . var_export($groupTransactionAmount, true));
                $this->payRemainder = $amount - $groupTransactionAmount;
                return $amount - $groupTransactionAmount;
            }

            if ($amount > $totalOrder) {
                return $totalOrder;
            }
            return $amount;
        }
        return 0;
    }

    protected function handleShippingAddressByMyParcel($payment, &$requestData)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');
        $myparcelFetched = false;
        if ($myparcelOptions = $payment->getOrder()->getData('myparcel_delivery_options')) {
            if (!empty($myparcelOptions)) {
                try {
                    $myparcelOptions = json_decode($myparcelOptions, true);
                    $isPickup        = $myparcelOptions['isPickup'] ?? false;
                    if ($isPickup) {
                        $this->updateShippingAddressByMyParcel($myparcelOptions['pickupLocation'], $requestData);
                        $myparcelFetched = true;
                    }
                } catch (\JsonException $je) {
                    $this->logger2->addDebug(__METHOD__ . '|2|' . ' Error related to json_decode (MyParcel plugin compatibility)');
                }
            }
        }

        if (!$myparcelFetched) {
            $this->logger2->addDebug(__METHOD__ . '|10|');
            if ((strpos((string)$payment->getOrder()->getShippingMethod(), 'myparcelnl') !== false)
                &&
                (strpos((string)$payment->getOrder()->getShippingMethod(), 'pickup') !== false)
            ) {
                $this->logger2->addDebug(__METHOD__ . '|15|');
                if ($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()) {
                    if ($myParcelNLData = $this->helper->getJson()->unserialize($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData())) {
                        $this->logger2->addDebug(__METHOD__ . '|20|');
                        $this->updateShippingAddressByMyParcel($myParcelNLData, $requestData);
                    }
                }
            }
        }
    }

    protected function updateShippingAddressByMyParcel($myParcelLocation, &$requestData)
    {
        $mapping = [
            ['ShippingStreet', $myParcelLocation['street']],
            ['ShippingPostalCode', $myParcelLocation['postal_code']],
            ['ShippingCity', $myParcelLocation['city']],
            ['ShippingCountryCode', $myParcelLocation['cc']],
            ['ShippingHouseNumber', $myParcelLocation['number']],
            ['ShippingHouseNumberSuffix', $myParcelLocation['number_suffix']],
        ];

        $this->logger2->addDebug(__METHOD__ . '|1|' . var_export($mapping, true));

        $this->updateShippingAddressCommonMappingV2($mapping, $requestData);
    }

    protected function updateShippingAddressByMyParcelV2($myParcelLocation, &$requestData)
    {
        $mapping = [
            ['Street', $myParcelLocation['street']],
            ['PostalCode', $myParcelLocation['postal_code']],
            ['City', $myParcelLocation['city']],
            ['Country', $myParcelLocation['cc']],
            ['StreetNumber', $myParcelLocation['number']],
            ['StreetNumberAdditional', $myParcelLocation['number_suffix']],
        ];

        $this->logger2->addDebug(__METHOD__ . '|1|' . var_export($mapping, true));

        $this->updateShippingAddressCommonMapping($mapping, $requestData);
    }

    public function getServiceCostLine($latestKey, $order, &$itemsTotalAmount = 0)
    {
        $buckarooFeeLine = $order->getBuckarooFeeInclTax();

        if (!$buckarooFeeLine && ($order->getBuckarooFee() >= 0.01)) {
            $this->logger2->addDebug(__METHOD__ . '|5|');
            $buckarooFeeLine = $order->getBuckarooFee();
        }

        $article = [];

        if (false !== $buckarooFeeLine && (double) $buckarooFeeLine > 0) {
            $article = $this->getArticleArrayLine(
                $latestKey,
                'Servicekosten',
                1,
                1,
                round($buckarooFeeLine, 2),
                $this->getTaxCategory($order)
            );
            $itemsTotalAmount += round($buckarooFeeLine, 2);
        }

        return $article;
    }

    public function getArticleArrayLine(
        $latestKey,
        $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ) {
    }

    protected function getTaxCategory($order)
    {
        $request    = $this->taxCalculation->getRateRequest(null, null, null, $order->getStore());
        $taxClassId = $this->configProviderBuckarooFee->getTaxClass($order->getStore());
        $percent    = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));
        return $percent;
    }

    protected function getShippingAmount($order)
    {
        return $order->getShippingInclTax();
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return float|int
     */
    protected function getDiscountAmount($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $discount = 0;
        $edition  = $this->softwareData->getProductMetaData()->getEdition();

        if ($order->getDiscountAmount() < 0) {
            $discount -= abs((double) $order->getDiscountAmount());
        }

        if ($edition == 'Enterprise' && $order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((double) $order->getCustomerBalanceAmount());
        }

        return $discount;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $productItem
     * @param                                 $includesTax
     *
     * @return mixed
     */
    public function calculateProductPrice($productItem, $includesTax)
    {
        $productPrice = $productItem->getPriceInclTax();

        if (!$includesTax) {
            if ($productItem->getDiscountAmount() >= 0.01) {
                $productPrice = $productItem->getPrice()
                 + $productItem->getTaxAmount() / $productItem->getQty();
            }
        }

        if ($productItem->getWeeeTaxAppliedAmount() > 0) {
            $productPrice += $productItem->getWeeeTaxAppliedAmount();
        }

        return $productPrice;
    }

    public function getRefundType($count)
    {
        $article = [
            [
                '_'       => 'Refund',
                'Name'    => 'RefundType',
                'GroupID' => $count,
                'Group'   => 'Article',
            ],
        ];

        return $article;
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
        $billingAddress  = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData  = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

        return !empty($arrayDifferences);
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return boolean
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
            'quote_address_id'
        ]);

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        $arrayDiff          = array_diff($filteredAddressOne, $filteredAddressTwo);

        return $arrayDiff;
    }

    protected function getDiffLine($latestKey, $diff)
    {
        $article = $this->getArticleArrayLine(
            $latestKey,
            'Discount/Fee',
            1,
            1,
            round($diff, 2),
            4
        );

        return $article;
    }

    protected function updateShippingAddressBySendcloud($order, &$requestData)
    {
        if ($order->getSendcloudServicePointId() > 0) {
            foreach ($requestData as $key => $value) {
                if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                    $mapping = [
                        ['Street', $order->getSendcloudServicePointStreet()],
                        ['PostalCode', $order->getSendcloudServicePointZipCode()],
                        ['City', $order->getSendcloudServicePointCity()],
                        ['Country', $order->getSendcloudServicePointCountry()],
                        ['StreetNumber', $order->getSendcloudServicePointHouseNumber()],
                    ];
                    foreach ($mapping as $mappingItem) {
                        if (($requestData[$key]['Name'] == $mappingItem[0]) && !empty($mappingItem[1])) {
                            $requestData[$key]['_'] = $mappingItem[1];
                        }
                    }

                    if ($requestData[$key]['Name'] == 'StreetNumberAdditional') {
                        unset($requestData[$key]);
                    }

                }
            }
        }
    }

    /**
     * Check if there is a "pakjegemak" address stored in the quote by this order.
     * Afterpay wants to receive the "pakjegemak" address instead of the customer shipping address.
     *
     * @param int $quoteId
     *
     * @return array|\Magento\Quote\Model\Quote\Address
     */
    protected function getPostNLPakjeGemakAddressInQuote($quoteId)
    {
        $quoteAddress = $this->addressFactory->create();

        $collection = $quoteAddress->getCollection();
        $collection->addFieldToFilter('quote_id', $quoteId);
        $collection->addFieldToFilter('address_type', 'pakjegemak');
        // @codingStandardsIgnoreLine
        return $collection->setPageSize(1)->getFirstItem();
    }

    protected function updateShippingAddressCommonMapping(array $mapping, array &$requestData)
    {
        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                        if ($requestData[$key]['Name'] == $mappingItem[0]) {
                            $requestData[$key]['_'] = $mappingItem[1];
                            $found                  = true;
                        }
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_'       => $mappingItem[1],
                        'Name'    => $mappingItem[0],
                        'Group'   => 'ShippingCustomer',
                        'GroupID' => '',
                    ];
                }
            }
        }
    }

    protected function updateShippingAddressCommonMappingV2(array $mapping, array &$requestData)
    {
        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Name'] == $mappingItem[0]) {
                        $requestData[$key]['_'] = $mappingItem[1];
                        $found                  = true;
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_'    => $mappingItem[1],
                        'Name' => $mappingItem[0],
                    ];
                }
            }
        }
    }

    public function updateShippingAddressByDpdParcel($quote, &$requestData)
    {
        $fullStreet = $quote->getDpdStreet();
        $postalCode = $quote->getDpdZipcode();
        $city       = $quote->getDpdCity();
        $country    = $quote->getDpdCountry();

        if (!$fullStreet && $quote->getDpdParcelshopId()) {
            $this->logger2->addDebug(__METHOD__ . '|2|');
            $this->logger2->addDebug(var_export($_COOKIE, true));
            $fullStreet = $_COOKIE['dpd-selected-parcelshop-street'] ?? '';
            $postalCode = $_COOKIE['dpd-selected-parcelshop-zipcode'] ?? '';
            $city       = $_COOKIE['dpd-selected-parcelshop-city'] ?? '';
            $country    = $_COOKIE['dpd-selected-parcelshop-country'] ?? '';
        }

        $matches = false;
        if ($fullStreet && preg_match('/(.*)\s(.+)$/', $fullStreet, $matches)) {
            $this->logger2->addDebug(__METHOD__ . '|3|');

            $street            = $matches[1];
            $streetHouseNumber = $matches[2];

            $mapping = [
                ['Street', $street],
                ['PostalCode', $postalCode],
                ['City', $city],
                ['Country', $country],
                ['StreetNumber', $streetHouseNumber],
            ];

            $this->logger2->addDebug(var_export($mapping, true));

            $this->updateShippingAddressCommonMapping($mapping, $requestData);

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
        $this->logger2->addDebug(__METHOD__ . '|1|');

        $matches = [];
        if (preg_match('/^(.*)-([A-Z]{2})-(.*)$/', $servicePointId, $matches)) {
            $curl = $this->objectManager->get('Magento\Framework\HTTP\Client\Curl');
            $curl->get('https://api-gw.dhlparcel.nl/parcel-shop-locations/' . $matches[2] . '/' . $servicePointId);
            if (($response = $curl->getBody())
                &&
                //phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
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
                            ['StreetNumberAdditional', 'addition'],
                        ];
                        foreach ($mapping as $mappingItem) {
                            if (($requestData[$key]['Name'] == $mappingItem[0]) && (!empty($parsedResponse->address->{$mappingItem[1]}))) {
                                if ($mappingItem[1] == 'addition') {
                                    $parsedResponse->address->{$mappingItem[1]} =
                                    $this->cleanStreetNumberAddition($parsedResponse->address->{$mappingItem[1]});
                                }
                                $requestData[$key]['_'] = $parsedResponse->address->{$mappingItem[1]};
                            }
                        }

                    }
                }
            }
        }
    }

    private function cleanStreetNumberAddition($addition)
    {
        return preg_replace('/[\W]/', '', $addition);
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
            'street'          => $street,
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
     * If we have already paid some value we do a pay reminder request
     *
     * @param Payment $payment
     * @param TransactionBuilderInterface $transactionBuilder
     * @param string $serviceAction
     * @param string $newServiceAction
     *
     * @return void
     */
    protected function getPayRemainder($payment, $transactionBuilder, $serviceAction = 'Pay', $newServiceAction = 'PayRemainder')
    {
        /** @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction */
        $paymentGroupTransaction = $this->objectManager->create('\Buckaroo\Magento2\Helper\PaymentGroupTransaction');
        $incrementId = $payment->getOrder()->getIncrementId();

        $alreadyPaid = $paymentGroupTransaction->getAlreadyPaid($incrementId);

        if ($alreadyPaid > 0) {
            $serviceAction = $newServiceAction;

            $this->payRemainder = $this->getPayRemainderAmount($payment, $alreadyPaid);
            $transactionBuilder->setAmount($this->payRemainder);
            $transactionBuilder->setOriginalTransactionKey(
                $paymentGroupTransaction->getGroupTransactionOriginalTransactionKey($incrementId)
            );
        }
        return $serviceAction;
    }

    protected function getPayRemainderAmount($payment, $alreadyPaid)
    {
        return $payment->getOrder()->getGrandTotal() - $alreadyPaid;
    }

    protected function getRequestArticlesDataPayRemainder($payment)
    {
        return $this->getArticleArrayLine(
            1,
            'PayRemainder',
            1,
            1,
            round($this->payRemainder, 2),
            $this->getTaxCategory($payment->getOrder())
        );
    }

    protected function getCreditmemoArticleDataPayRemainder($payment, $addRefundType = true)
    {
        $article = $this->getArticleArrayLine(
            1,
            'PayRemainder',
            1,
            1,
            round($this->payRemainder, 2),
            $this->getTaxCategory($payment->getOrder())
        );
        if ($addRefundType) {
            $article[] = [
                '_'       => 'Refund',
                'Name'    => 'RefundType',
                'GroupID' => 1,
                'Group'   => 'Article',
            ];
        }
        return $article;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return '';
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

        $request    = $this->taxCalculation->getRateRequest(null, null, null);
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent    = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingCostsArticle = [
            [
                '_'       => 'Shipping fee',
                'Name'    => 'Description',
                'Group'   => 'Article',
                'GroupID' => $count,
            ],
            [
                '_'       => $this->formatPrice($shippingAmount),
                'Name'    => $this->getPriceFieldName(),
                'Group'   => 'Article',
                'GroupID' => $count,
            ],
            [
                '_'       => $this->formatShippingCostsLineVatPercentage($percent),
                'Name'    => 'VatPercentage',
                'Group'   => 'Article',
                'GroupID' => $count,
            ],
            [
                '_'       => '1',
                'Name'    => 'Quantity',
                'Group'   => 'Article',
                'GroupID' => $count,
            ],
            [
                '_'       => '1',
                'Name'    => 'Identifier',
                'Group'   => 'Article',
                'GroupID' => $count,
            ],
        ];

        $itemsTotalAmount += $shippingAmount;

        return $shippingCostsArticle;
    }

    protected function getPriceFieldName()
    {
        return 'GrossUnitPrice';
    }

    protected function formatPrice($price)
    {
        return $price;
    }

    protected function formatShippingCostsLineVatPercentage($percent)
    {
        return $percent;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getPaymentRequestParameters($payment)
    {
        // First data to set is the billing address data.
        $requestData = $this->getRequestBillingData($payment);


        // If the shipping address is not the same as the billing it will be merged inside the data array.
        if (
            $this->isAddressDataDifferent($payment) ||
            is_null($payment->getOrder()->getShippingAddress()) ||
            $payment->getMethod() === Klarna::KLARNA_METHOD_NAME  ||
            $payment->getMethod() === Klarnain::PAYMENT_METHOD_CODE ||
            $payment->getMethod() === Afterpay20::PAYMENT_METHOD_CODE
        ) {
            $requestData = array_merge($requestData, $this->getRequestShippingData($payment));
        }
        $this->logger2->addDebug(__METHOD__ . '|1|');
        $this->logger2->addDebug(var_export($payment->getOrder()->getShippingMethod(), true));

        if ($payment->getOrder()->getShippingMethod() == 'dpdpickup_dpdpickup') {
            $quote = $this->quoteFactory->create()->load($payment->getOrder()->getQuoteId());
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

        if (
            ($payment->getOrder()->getShippingMethod() == 'sendcloud_sendcloud')
            &&
            $payment->getOrder()->getSendcloudServicePointId()
        ) {
            $this->updateShippingAddressBySendcloud($payment->getOrder(), $requestData);
        }

        $this->handleShippingAddressByMyParcel($payment, $requestData);

        // Merge the article data; products and fee's
        $requestData = array_merge($requestData, $this->getRequestArticlesData($payment));

        $requestData = $this->checkTotalGrossAmount($requestData, $payment);

        return $requestData;
    }

    public function getShippingAddress($payment, &$isAbsent = false)
    {
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if (!$shippingAddress) {
            $isAbsent        = true;
            $shippingAddress = $payment->getOrder()->getBillingAddress();
        }
        return $shippingAddress;
    }

    public function checkTotalGrossAmount($requestData, $payment)
    {
        $order            = $payment->getOrder();
        $itemsTotalAmount = 0;
        $count            = 1;
        $requestData2     = [];
        foreach ($requestData as $item) {
            if (isset($item['GroupID']) && $item['GroupID'] > 0) {
                if ($item['Name'] == 'Quantity') {
                    $requestData2[$item['GroupID']]['Quantity'] = $item['_'];
                }
                if ($item['Name'] == $this->getPriceFieldName()) {
                    $requestData2[$item['GroupID']][$this->getPriceFieldName()] = $item['_'];
                }
            }
        }

        foreach ($requestData2 as $key => $item) {
            $itemsTotalAmount += $item['Quantity'] * $item[$this->getPriceFieldName()];
            $count++;
        }

        //Add diff line
        if (!$this->helper->areEqualAmounts($order->getGrandTotal(), $itemsTotalAmount) && !$this->payRemainder) {
            $diff        = $order->getGrandTotal() - $itemsTotalAmount;
            $diffLine    = $this->getDiffLine($count, $diff);
            $requestData = array_merge($requestData, $diffLine);
        }

        return $requestData;

    }
    public function canUseForCountry($country)
    {
        if ($this->getConfigData('allowspecific') != 1) {
            return true;
        }

        $specificCountries = $this->getConfigData('specificcountry');

        //if the country config is null in the store get the config value from the global('default') settings
        if ($specificCountries === null) {
            $specificCountries = $this->_scopeConfig->getValue(
                'payment/' . $this->getCode() . '/specificcountry',
            );
        }

        if (empty($specificCountries)) return false;
        $availableCountries = explode(',', $specificCountries);
        return in_array($country, $availableCountries);
    }

    /**
     * Cancel previous order that comes from a restored quote
     *
     * @param InfoInterface $payment
     *
     * @return void
     */
    private function cancelPreviousPendingOrder(InfoInterface $payment)
    {
        try {
            $orderId = $payment->getAdditionalInformation('buckaroo_cancel_order_id');

            if (is_null($orderId)) {
                return;
            }

            /** @var \Magento\Sales\Api\OrderRepositoryInterface */
            $orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);

            $order = $orderRepository->get((int)$orderId);

            if($order->getState() === Order::STATE_NEW) {
                $orderManagement = $this->objectManager->get(OrderManagementInterface::class);
                $orderManagement->cancel($order->getEntityId());
                $order->addCommentToStatusHistory(
                    __('Canceled on browser back button')
                )
                ->setIsCustomerNotified(false)
                ->setEntityName('invoice')
                ->save();
            }


        } catch (\Throwable $th) {
            $this->logger2->addError(__METHOD__." ".(string)$th);
        }
        
    }

    /**
     * Check if config spam limit is active
     *
     * @return boolean
     */
    private function isSpamLimitActive(): bool
    {
        return $this->getConfigData('spam_prevention') == 1;
    }

    /**
     * Update session when a failed attempt is made for the quote & method
     *
     * @return void
     */
    private function updateRateLimiterCount() {

        if (!$this->isSpamLimitActive()) {
            return;
        }

        $method = $this->getCode();
        $quoteId = $this->helper->getQuote()->getId();
        $checkoutSession = $this->helper->getCheckoutSession();
        $storage = $this->getPaymentAttemptsStorage();
        if(!isset($storage[$quoteId])) {
            $storage[$quoteId] = [$method => 0];
        }

        if(!isset($storage[$quoteId][$method])) {
            $storage[$quoteId][$method] = 0;
        }

        $storage[$quoteId][$method]++;

        $checkoutSession->setBuckarooRateLimiterStorage(
            json_encode($storage)
        );
        $this->checkForSpamLimitReach($storage);
    }

    /**
     * Check if the spamming limit is reached
     *
     * @param array $storage
     *
     * @return void
     * @throws LimitReachException
     */
    private function checkForSpamLimitReach($storage)
    {
        $limitReachMessage = __('Cannot create order, maximum payment attempts reached');

        $storedReachMessage = $this->getConfigData('spam_message');

        if(is_string($storedReachMessage) && trim($storedReachMessage) > 0) {
            $limitReachMessage = $storedReachMessage;
        }

        if($this->isSpamLimitReached($storage)) {
            throw new LimitReachException($limitReachMessage);
        }
    }

    /**
     * Check if the spam limit is reached
     *
     * @param array $storage
     *
     * @return boolean
     */
    private function isSpamLimitReached($storage)
    {
        if ($this->getConfigData('spam_prevention') != 1) {
            return false;
        }

        $limit = $this->getConfigData('spam_attempts');
        
        if(!is_scalar($limit)) {
            $limit = 10;
        }
        $limit = intval($limit);

        $method = $this->getCode();
        $quoteId = $this->helper->getQuote()->getId();

        $attempts = 0;
        if(isset($storage[$quoteId][$method])) {
            $attempts = $storage[$quoteId][$method];
        }

        return $attempts >= $limit;
    }

    /**
     * Retrieve and format number of payment attempts
     *
     * @return array
     */
    private function getPaymentAttemptsStorage(): array
    {
        $checkoutSession = $this->helper->getCheckoutSession();
        $storage = $checkoutSession->getBuckarooRateLimiterStorage();

        if($storage === null) {
            return [];
        } 

        $storage = json_decode($storage, true);

        
        if(!is_array($storage)) {
            $storage = [];
        }

        return $storage;
    }

    /**
     * Update payment with the user message, update session in order to restore the quote
     *
     * @param mixed $payment
     * @param string $message
     *
     * @return void
     */
    private function setMaxAttemptsFlags($payment, string $message)
    {
        $this->helper->setRestoreQuoteLastOrder($payment->getOrder()->getId());
        $this->helper->getCheckoutSession()->setBuckarooFailedMaxAttempts(true);
        $payment->setAdditionalInformation(self::PAYMENT_ATTEMPTS_REACHED_MESSAGE, $message);
    }
}
