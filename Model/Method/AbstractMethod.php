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

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Logging\Log;
use mysql_xdevapi\Exception;

abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    const BUCKAROO_ALL_TRANSACTIONS = 'buckaroo_all_transactions';

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
    public $closeOrderTransaction       = true;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction   = true;

    /**
     * @var bool
     */
    public $closeCaptureTransaction     = true;

    /**
     * @var bool
     */
    public $closeRefundTransaction      = true;

    /**
     * @var bool
     */
    public $closeCancelTransaction      = true;

    /**
     * @var bool|string
     */
    public $orderPlaceRedirectUrl       = true;

    /**
     * @var bool
     */
    public $usesRedirect            = true;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    // @codingStandardsIgnoreStart
    /**
     * @var string
     */
    protected $_infoBlockType = 'Buckaroo\Magento2\Block\Info';
    // @codingStandardsIgnoreEnd

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Developer\Helper\Data
     */
    protected $developmentHelper;

    /**
     * @var null
     */
    public $remoteAddress = null;

    protected $logger2;

    public static $requestOnVoid = true;

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
        $this->objectManager                = $objectManager;
        $this->gateway                      = $gateway;
        $this->transactionBuilderFactory    = $transactionBuilderFactory;
        $this->validatorFactory             = $validatorFactory; //Move to gateway?
        $this->helper                       = $helper;
        $this->request                      = $request;
        $this->refundFieldsFactory          = $refundFieldsFactory;
        $this->configProviderFactory        = $configProviderFactory; //Account and Refund used
        $this->configProviderMethodFactory  = $configProviderMethodFactory; //Load interface, inject childs via di?
        $this->priceHelper                  = $priceHelper;
        $this->developmentHelper            = $developmentHelper;

        $this->logger2 = $objectManager->create('Buckaroo\Magento2\Logging\Log');

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
            $storeId = $quote ? $quote->getStoreId() : null;
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
        $allowedCurrencies = explode(',', $allowedCurrenciesRaw);

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

        $response = $this->orderTransaction($transaction);

        $this->saveTransactionData($response[0], $payment, $this->closeOrderTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

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
        $responseCode = $transactionResponse->Status->Code->Code;
        $billingCountry = $this->payment->getOrder()->getBillingAddress()->getCountryId();

        $method = null;
        if ($this->payment->getMethodInstance() && !empty($this->payment->getMethodInstance()->buckarooPaymentMethodCode)) {
            $method = $this->payment->getMethodInstance()->buckarooPaymentMethodCode;
        }
        if ($method == 'trustly') {
            $methodMessage = $this->getFailureMessageFromMethod($transactionResponse);
            $message = strlen($methodMessage) > 0 ? $methodMessage : $message;
            return $message;
        }

        $allowedResponseCodes = [490, 690];

        if ($billingCountry == 'NL' && in_array($responseCode, $allowedResponseCodes)) {
            $methodMessage = $this->getFailureMessageFromMethod($transactionResponse);
            $message = strlen($methodMessage) > 0 ? $methodMessage : $message;
        }

        if(isset($transactionResponse->Status->SubCode->_)){
            $message = $transactionResponse->Status->SubCode->_;
        }

        return $message;
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

        $response = $this->authorizeTransaction($transaction);

        $this->saveTransactionData($response[0], $payment, $this->closeAuthorizeTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

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
        $this->logger2->addDebug(__METHOD__.'|1|');

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
        $this->logger2->addDebug(__METHOD__.'|1|');

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
        $this->logger2->addDebug(__METHOD__.'|1|');

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

        $this->logger2->addDebug(__METHOD__.'|5|');

        $this->payment = $payment;
        $paymentCm3InvoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (strlen($paymentCm3InvoiceKey) > 0) {
            $this->createCreditNoteRequest($payment);
        }

        $amount = $this->refundGroupTransactions($payment, $amount);

        $this->logger2->addDebug(__METHOD__.'|10|'.var_export($amount, true));

        if($amount<=0) {
            return $this;
        }

        $transactionBuilder = $this->getRefundTransactionBuilder($payment);

        if (!$transactionBuilder) {
            $this->logger2->addDebug(__METHOD__.'|20|');
            throw new \LogicException(
                'Refund action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            $this->logger2->addDebug(__METHOD__.'|25|');
            return $this;
        }
        $transactionBuilder->setAmount($amount);

        $transaction = $transactionBuilder->build();

        $response = $this->refundTransaction($transaction);

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
    public function refundTransaction(\Buckaroo\Magento2\Gateway\Http\Transaction $transaction)
    {
        $response = $this->gateway->refund($transaction);

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
        $this->logger2->addDebug(__METHOD__.'|1|');
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
            $this->logger2->addDebug(__METHOD__.'|5|');
            return $this;
        }

        if (self::$requestOnVoid) {
            $this->logger2->addDebug(__METHOD__.'|10|');
            $transactionBuilder = $this->getVoidTransactionBuilder($payment);
        } else {
            $this->logger2->addDebug(__METHOD__.'|15|');
            $transactionBuilder = true;
        }

        if (!$transactionBuilder) {
            $this->logger2->addDebug(__METHOD__.'|20|');
            throw new \LogicException(
                'Void action is not implemented for this payment method.'
            );
        } elseif ($transactionBuilder === true) {
            $this->logger2->addDebug(__METHOD__.'|25|');
            return $this;
        }

        $this->logger2->addDebug(__METHOD__.'|30|');

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

        $registryValue = $this->_registry->registry($key);
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
                'payment' => $payment,
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

            /**
             * Save the payment's transaction key.
             */
            if ($saveId) {
                $payment->setAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey);
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
        $services = [];

        if (empty($requestParams['creditmemo'])) {
            return $services;
        }

        $creditMemoParams = $requestParams['creditmemo'];
        $extraFields = $this->refundFieldsFactory->get($paymentMethodCode);

        if (empty($extraFields)) {
            return $services;
        }

        /**
         * If extra fields are found, attach these as 'RequestParameter' to the services.
         */
        foreach ($extraFields as $extraField) {
            $code = $extraField['code'];
            $services['RequestParameter'][] = [
                '_' => "$creditMemoParams[$code]",
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
    abstract public function getCaptureTransactionBuilder($payment);

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    abstract public function getRefundTransactionBuilder($payment);

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface|bool
     */
    abstract public function getVoidTransactionBuilder($payment);

    public function refundGroupTransactions(InfoInterface $payment, $amount)
    {
        $this->logger2->addDebug(__METHOD__.'|1|');

        $order = $payment->getOrder();
        $totalOrder = $order->getBaseGrandTotal();
        $paymentGroupTransaction = $this->objectManager->create('\Buckaroo\Magento2\Helper\PaymentGroupTransaction');

        $requestParams = $this->request->getParams();
        if(isset($requestParams['creditmemo']) && !empty($requestParams['creditmemo']['buckaroo_already_paid'])){
            foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $transaction => $amount_value) {

                $transaction = explode('|',$transaction);
                $totalOrder = $totalOrder - $transaction[2];

                $groupTransaction = $paymentGroupTransaction->getGroupTransactionByTrxId($transaction[0]);

                $this->logger2->addDebug(__METHOD__.'|10|'.var_export([$amount_value, $amount], true));

                if($amount_value>0 && $amount>0){
                    if($amount<$amount_value){$amount_value=$amount;}
                    $amount = $amount - $amount_value;
                    $this->logger2->addDebug(__METHOD__.'|15|'.var_export([$amount], true));
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
                    $response = $this->refundTransaction($transaction);

                    $this->logger2->addDebug(__METHOD__.'|16| '.var_export($response, true));
                    $arrayResponse = json_decode(json_encode($response), true);

                    $this->saveTransactionData($response[0], $payment, $this->closeRefundTransaction, false);

                    foreach ($groupTransaction as $item) {
                        if (!empty(floatval($item['refunded_amount']))) {
                            $item['refunded_amount'] += $amount_value;
                        } else {
                            $item['refunded_amount'] = $amount_value;
                        }
                        $paymentGroupTransaction->updateGroupTransaction($item->_data);
                    }
//                    $refundedAmount = $this->groupTransaction->getRefundedAmount();

                }
            }
        }

        $this->logger2->addDebug(__METHOD__.'|20|'.var_export([$amount, $totalOrder, $amount>=0.01], true));

        if($amount>=0.01){
            $groupTransactionAmount = $paymentGroupTransaction->getGroupTransactionAmount($order->getIncrementId());
            if($amount == $order->getBaseGrandTotal() && $groupTransactionAmount > 0){
                return $amount - $groupTransactionAmount;
            }

            if($amount>$totalOrder){
                return $totalOrder;
            }
            return $amount;
        }
        return 0;
    }

    protected function handleShippingAddressByMyParcel($payment, &$requestData)
    {
        $this->logger2->addDebug(__METHOD__.'|1|');
        $myparcelFetched = false;
        if ($myparcelOptions = $payment->getOrder()->getData('myparcel_delivery_options')) {
            if (!empty($myparcelOptions)) {
                try{
                    $myparcelOptions = json_decode($myparcelOptions, true);
                    $isPickup = $myparcelOptions['isPickup'] ?? false;
                    if ($isPickup) {
                        $this->updateShippingAddressByMyParcel($myparcelOptions['pickupLocation'], $requestData);
                        $myparcelFetched = true;
                    }
                } catch (\JsonException $je) {
                    $this->logger2->addDebug(__METHOD__.'|2|'.' Error related to json_decode (MyParcel plugin compatibility)');
                }
            }
        }

        if (!$myparcelFetched) {
            $this->logger2->addDebug(__METHOD__.'|10|');
            if (
                (strpos($payment->getOrder()->getShippingMethod(), 'myparcelnl') !== false)
                &&
                (strpos($payment->getOrder()->getShippingMethod(), 'pickup') !== false)
            ) {
                $this->logger2->addDebug(__METHOD__.'|15|');
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

        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Name'] == $mappingItem[0]) {
                        $requestData[$key]['_'] = $mappingItem[1];
                        $found = true;
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_'    => $mappingItem[1],
                        'Name' => $mappingItem[0]
                    ];
                }
            }
        }
    }
}

