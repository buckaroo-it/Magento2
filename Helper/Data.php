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

namespace Buckaroo\Magento2\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Data
 *
 * @package Buckaroo\Magento2\Helper
 */
class Data extends AbstractHelper
{
    const MODE_INACTIVE = 0;
    const MODE_TEST     = 1;
    const MODE_LIVE     = 2;

    /**
     * Buckaroo_Magento2 status codes
     *
     * @var array $statusCode
     */
    protected $statusCodes = [
        'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'               => 190,
        'BUCKAROO_MAGENTO2_STATUSCODE_FAILED'                => 490,
        'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE'    => 491,
        'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR'       => 492,
        'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'              => 690,
        'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT' => 790,
        'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'    => 791,
        'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER'   => 792,
        'BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD'       => 793,
        'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'     => 890,
        'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT' => 891,

        /**
         * Codes below are created by dev, not by Buckaroo.
         */
        'BUCKAROO_MAGENTO2_ORDER_FAILED'                     => 11014,
    ];

    protected $debugConfig = [];

    /**
     * @var Account
     */
    public $configProviderAccount;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var \Magento\Framework\HTTP\Header
     */
    protected $httpHeader;

    /** @var CheckoutSession */
    protected $_checkoutSession;
    protected $_checkoutSessionProxy;

    protected $groupTransaction;

    protected $logger;

    protected $customerRepository;

    private $staticCache = [];

    /** @var StoreManagerInterface */
    private $storeManager;

    private $scopeDefiner;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * @param Context $context
     * @param Account $configProviderAccount
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Factory $configProviderMethodFactory,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Model\Session\Proxy $checkoutSessionProxy,
        PaymentGroupTransaction $groupTransaction,
        Log $logger,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        \Magento\Config\Model\Config\ScopeDefiner $scopeDefiner,
        \Magento\Framework\Serialize\Serializer\Json $json

    ) {
        parent::__construct($context);

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->httpHeader = $httpHeader;
        $this->_checkoutSession  = $checkoutSession;
        $this->_checkoutSessionProxy  = $checkoutSessionProxy;
        $this->groupTransaction  = $groupTransaction;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->scopeDefiner = $scopeDefiner;
        $this->json = $json;
    }

    /**
     * Return the requested status $code, or null if not found
     *
     * @param $code
     *
     * @return int|null
     */
    public function getStatusCode($code)
    {
        if (isset($this->statusCodes[$code])) {
            return $this->statusCodes[$code];
        }
        return null;
    }

    /**
     * Return the requested status key with the value, or null if not found
     *
     * @param int $value
     *
     * @return mixed|null
     */
    public function getStatusByValue($value)
    {
        $result = array_search($value, $this->statusCodes);
        if (!$result) {
            $result = null;
        }
        return $result;
    }

    /**
     * Return all status codes currently set
     *
     * @return array
     */
    public function getStatusCodes()
    {
        return $this->statusCodes;
    }

    /**
     * @param array  $array
     * @param array  $rawInfo
     * @param string $keyPrefix
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array, $rawInfo = [], $keyPrefix = '')
    {
        foreach ($array as $key => $value) {
            $key = $keyPrefix . $key;

            if (is_array($value)) {
                $rawInfo = $this->getTransactionAdditionalInfo($value, $rawInfo, $key . ' => ');
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $rawInfo[$key] = $value;
        }

        return $rawInfo;
    }

    /**
     * @param null|string $paymentMethod
     *
     * @return int
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getMode($paymentMethod = null, $store = null)
    {
        $baseMode =  $this->configProviderAccount->getActive();

        if (!$paymentMethod || !$baseMode) {
            return $baseMode;
        }

        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider $configProvider
         */
        $configProvider = $this->configProviderMethodFactory->get($paymentMethod);
        if ($store === null) {
            $mode = $configProvider->getActive();
        } else {
            $mode = $configProvider->getActive($store);
        }

        return $mode;
    }

    /**
     * Return if browser is in mobile mode
     *
     * @return array
     */
    public function isMobile()
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        return \Zend_Http_UserAgent_Mobile::match($userAgent, $_SERVER);
    }

    public function getOriginalTransactionKey($orderId){
        $originalTransactionKey = $this->_checkoutSession->getOriginalTransactionKey();
        return isset($originalTransactionKey[$orderId]) ? $originalTransactionKey[$orderId] : false;
    }

    public function getBuckarooAlreadyPaid($orderId){
        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();
        return isset($alreadyPaid[$orderId]) ? $alreadyPaid[$orderId] : false;
    }

    public function getOrderId(){
        $orderId = $this->_checkoutSession->getQuote()->getReservedOrderId();
        if(!$orderId){
            $orderId = $this->_checkoutSession->getQuote()->reserveOrderId()->getReservedOrderId();
            $this->_checkoutSession->getQuote()->save();
        }
        return $orderId;
    }

    public function isGroupTransaction(){
        if($this->groupTransaction->isGroupTransaction($orderId = $this->getOrderId())){
            return true;
        }
        return false;
    }

    public function getConfigCardSort() {
        $configValue = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_creditcard/sorted_creditcards',
            $this->scopeDefiner->getScope(),
            ($this->scopeDefiner->getScope() == ScopeInterface::SCOPE_WEBSITES) ? $this->storeManager->getStore() : null
        );

        return $configValue;
    }
    
    public function getConfigGiftCardsSort() {
        $configValue = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_giftcards/sorted_giftcards',
            $this->scopeDefiner->getScope(),
            ($this->scopeDefiner->getScope() == ScopeInterface::SCOPE_WEBSITES) ? $this->storeManager->getStore() : null
        );

        return $configValue;
    }

    /**
     * try to fetch customer details for PPE method in admin area
     *
     * @return array
     */
    public function getPPeCustomerDetails()
    {
        $this->logger->addDebug(__METHOD__ . '|1|' . var_export($this->_getRequest()->getParams(),true));
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ($customerId > 0)) {
            $this->logger->addDebug(__METHOD__ . '|5|');
            if (!isset($this->staticCache['getPPeCustomerDetails'])) {
                if ($customer = $this->customerRepository->getById($customerId)) {
                    $this->logger->addDebug(__METHOD__ . '|15|');
                    $billingAddress = null;
                    if ($addresses = $customer->getAddresses()) {
                        foreach ($addresses as $address) {
                            if ($address->isDefaultBilling()) {
                                $billingAddress = $address;
                                break;
                            }
                        }
                    }
                    $this->logger->addDebug(var_export([$customer->getEmail()], true));
                    $this->staticCache['getPPeCustomerDetails'] = [
                        'email' => $customer->getEmail(),
                        'firstName' => $billingAddress ? $billingAddress->getFirstName() : '',
                        'lastName' => $billingAddress ? $billingAddress->getLastName() : '',
                    ];

                }
            }
        }

        if ($order = $this->_getRequest()->getParam('order')) {
            if (isset($order['billing_address'])) {
                $this->logger->addDebug(__METHOD__ . '|30|');
                $this->staticCache['getPPeCustomerDetails'] = [
                    'email' => !empty($this->staticCache['getPPeCustomerDetails']['email']) ? $this->staticCache['getPPeCustomerDetails']['email'] : '',
                    'firstName' => $order['billing_address']['firstname'],
                    'lastName' => $order['billing_address']['lastname'],
                ];
            }
        }

        if (($payment = $this->_getRequest()->getParam('payment')) && ($payment['method'] == 'buckaroo_magento2_payperemail')) {
            $this->logger->addDebug(__METHOD__ . '|40|');
            $this->staticCache['getPPeCustomerDetails'] = [
                'email' => $payment['customer_email'],
                'firstName' => $payment['customer_billingFirstName'],
                'lastName' => $payment['customer_billingLastName'],
            ];
        }

        return $this->staticCache['getPPeCustomerDetails'] ?? null;
    }

    public function areEqualAmounts($amount1, $amount2)
    {
        if ($amount2 == 0) {
            return $amount1 == $amount2;
        } else {
            return abs(
                    (floatval($amount1) - floatval($amount2))
                    / floatval($amount2)
                ) < 0.00001;
        }
    }

    public function getRestoreQuoteLastOrder(){
        return $this->_checkoutSessionProxy->getRestoreQuoteLastOrder();
    }

    public function setRestoreQuoteLastOrder($value){
        return $this->_checkoutSessionProxy->setRestoreQuoteLastOrder($value);
    }

    public function getQuote(){
        return $this->_checkoutSession->getQuote();
    }

    public function addDebug($messages){
        $this->logger->addDebug($messages);
    }

    public function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function getJson()
    {
        return $this->json;
    }
}
