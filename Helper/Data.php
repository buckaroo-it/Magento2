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

use Buckaroo\Magento2\Model\Config\Source\Business;
use Magento\Customer\Api\CustomerRepositoryInterface;
use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Group;
use Magento\Framework\App\Area;

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

    protected $_checkoutSession;

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

    private $state;
    private $customerSession;

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
        PaymentGroupTransaction $groupTransaction,
        Log $logger,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        \Magento\Config\Model\Config\ScopeDefiner $scopeDefiner,
        \Magento\Framework\Serialize\Serializer\Json $json,
        State $state,
        Session $customerSession
    ) {
        parent::__construct($context);

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->httpHeader = $httpHeader;
        $this->_checkoutSession  = $checkoutSession;
        $this->groupTransaction  = $groupTransaction;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->scopeDefiner = $scopeDefiner;
        $this->json = $json;
        $this->state = $state;
        $this->customerSession = $customerSession;
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
        $userAgent = new \Zend_Http_UserAgent;
        return \Zend_Http_UserAgent_Mobile::match($this->httpHeader->getHttpUserAgent(), $userAgent->getServer());
    }

    public function getOriginalTransactionKey($orderId)
    {
        $originalTransactionKey = $this->_checkoutSession->getOriginalTransactionKey();
        return isset($originalTransactionKey[$orderId]) ? $originalTransactionKey[$orderId] : false;
    }

    public function getBuckarooAlreadyPaid($orderId)
    {
        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();
        return isset($alreadyPaid[$orderId]) ? $alreadyPaid[$orderId] : false;
    }

    public function getOrderId()
    {
        $orderId = $this->_checkoutSession->getQuote()->getReservedOrderId();
        if (!$orderId) {
            $orderId = $this->_checkoutSession->getQuote()->reserveOrderId()->getReservedOrderId();
            $this->_checkoutSession->getQuote()->save();
        }
        return $orderId;
    }

    public function isGroupTransaction()
    {
        if ($this->groupTransaction->isGroupTransaction($orderId = $this->getOrderId())) {
            return true;
        }
        return false;
    }

    public function getConfigCardSort()
    {
        $configValue = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_creditcard/sorted_creditcards',
            $this->scopeDefiner->getScope(),
            ($this->scopeDefiner->getScope() == ScopeInterface::SCOPE_WEBSITES) ? $this->storeManager->getStore() : null
        );

        return $configValue;
    }
    
    public function getConfigGiftCardsSort()
    {
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
    //phpcs:ignore:Generic.Metrics.NestingLevel
    public function getPPeCustomerDetails()
    {
        $this->logger->addDebug(__METHOD__ . '|1|' . var_export($this->_getRequest()->getParams(), true));
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ($customerId > 0)) {
            $this->logger->addDebug(__METHOD__ . '|5|');
            if (!isset($this->staticCache['getPPeCustomerDetails'])
                && ($customer = $this->customerRepository->getById($customerId))
            ) {
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

        if ($order = $this->_getRequest()->getParam('order')) {
            if (isset($order['billing_address'])) {
                $this->logger->addDebug(__METHOD__ . '|30|');
                $this->staticCache['getPPeCustomerDetails'] = [
                    'email' => !empty($this->staticCache['getPPeCustomerDetails']['email']) ?
                        $this->staticCache['getPPeCustomerDetails']['email'] : '',
                    'firstName' => $order['billing_address']['firstname'],
                    'lastName' => $order['billing_address']['lastname'],
                ];
            }
        }

        if (($payment = $this->_getRequest()->getParam('payment'))
            && ($payment['method'] == 'buckaroo_magento2_payperemail')
        ) {
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
            return abs((floatval($amount1) - floatval($amount2)) / floatval($amount2)) < 0.00001;
        }
    }

    public function getRestoreQuoteLastOrder()
    {
        return $this->_checkoutSession->getRestoreQuoteLastOrder();
    }

    public function setRestoreQuoteLastOrder($value)
    {
        return $this->_checkoutSession->setRestoreQuoteLastOrder($value);
    }

    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    public function addDebug($messages)
    {
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

    public function getPaymentMethodsList()
    {
        return [
            ['value' => 'afterpay',               'label' => __('Afterpay (old)')],
            ['value' => 'afterpay2',       'label' => __('Afterpay 2 (old)')],
            ['value' => 'afterpay20',       'label' => __('Afterpay')],
            ['value' => 'alipay',       'label' => __('Alipay')],
            ['value' => 'applepay',       'label' => __('Apple Pay')],
            ['value' => 'billink',       'label' => __('Billink')],
            ['value' => 'capayablein3',       'label' => __('In3')],
            ['value' => 'creditcard',       'label' => __('Creditcards')],
            ['value' => 'creditcards',       'label' => __('Creditcards (Client sided)')],
            ['value' => 'emandate',       'label' => __('Digital Debit Authorization')],
            ['value' => 'eps',       'label' => __('EPS')],
            ['value' => 'giftcards',       'label' => __('Giftcards')],
            ['value' => 'giropay',       'label' => __('Giropay')],
            ['value' => 'ideal',       'label' => __('iDEAL')],
            ['value' => 'idealprocessing',       'label' => __('iDEAL Processing')],
            ['value' => 'kbc',       'label' => __('KBC')],
            ['value' => 'klarna',       'label' => __('Klarna Pay later (pay)')],
            ['value' => 'klarnain',       'label' => __('Klarna Slice it')],
            ['value' => 'klarnakp',       'label' => __('Klarna Pay later (authorize/capture)')],
            ['value' => 'mrcash',       'label' => __('Bancontact / Mister Cash')],
            ['value' => 'p24',       'label' => __('Przelewy24')],
            ['value' => 'payconiq',       'label' => __('Payconiq')],
            ['value' => 'paylink',       'label' => __('PayLink')],
            ['value' => 'paypal',       'label' => __('Paypal')],
            ['value' => 'payperemail',       'label' => __('PayPerEmail')],
            ['value' => 'pospayment',       'label' => __('Point of Sale')],
            ['value' => 'rtp',       'label' => __('Request To Pay')],
            ['value' => 'sepadirectdebit',       'label' => __('SEPA direct debit')],
            ['value' => 'sofortbanking',       'label' => __('SOFORT')],
            ['value' => 'belfius',       'label' => __('Belfius')],
            ['value' => 'tinka',       'label' => __('Tinka')],
            ['value' => 'transfer',       'label' => __('Bank Transfer')],
            ['value' => 'trustly',       'label' => __('Trustly')],
            ['value' => 'wechatpay',       'label' => __('WeChatPay')],
        ];
    }

    public function checkCustomerGroup(string $paymentMethod, bool $forceB2C = false): bool
    {
        if ($this->isBuckarooMethod($paymentMethod)) {
            $paymentMethodCode = $this->getBuckarooMethod($paymentMethod);
            $configProvider = $this->configProviderMethodFactory->get($paymentMethodCode);
            $configCustomerGroup = $configProvider->getSpecificCustomerGroup();

            if (!$forceB2C
                && (
                    ($paymentMethodCode == 'billink')
                    || (
                        (($paymentMethodCode == 'afterpay') || ($paymentMethodCode == 'afterpay2'))
                        && ($configProvider->getBusiness() == Business::BUSINESS_B2B)
                    )
                    || (
                        ($paymentMethodCode == 'payperemail') && ($configProvider->getEnabledB2B())
                    )
                )
            ) {
                $configCustomerGroup = $configProvider->getSpecificCustomerGroupB2B();

            }

            if ($configCustomerGroup === null) {
                return true;
            }

            if ($configCustomerGroup == -1) {
                return false;
            }

            if ($configCustomerGroup == Group::CUST_GROUP_ALL) {
                return true;
            }

            $configCustomerGroupArr = explode(',', $configCustomerGroup);

            if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
                return $this->checkCustomerGroupAdminArea($configCustomerGroupArr);
            } else {
                return $this->checkCustomerGroupFrontArea($configCustomerGroupArr);
            }
        }

        return true;
    }

    private function checkCustomerGroupAdminArea(array $configCustomerGroupArr): bool
    {
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ($customerId > 0)) {
            if ($customer = $this->customerRepository->getById($customerId)) {
                if ($customerGroup = $customer->getGroupId()) {
                    return in_array($customerGroup, $configCustomerGroupArr);
                }
            }
        }
        return true;
    }

    private function checkCustomerGroupFrontArea(array $configCustomerGroupArr): bool
    {
        if ($this->customerSession->isLoggedIn()) {
            if ($customerGroup = $this->customerSession->getCustomer()->getGroupId()) {
                return in_array($customerGroup, $configCustomerGroupArr);
            }
        } else {
            if (!in_array(Group::NOT_LOGGED_IN_ID, $configCustomerGroupArr)) {
                return false;
            }
        }
        return true;
    }

    public function isBuckarooMethod(string $paymentMethod): bool
    {
        return strpos($paymentMethod, 'buckaroo_magento2_') !== false;
    }

    public function getBuckarooMethod(string $paymentMethod): string
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $paymentMethod));
    }

    public function getOrderStatusByState($order, $orderState)
    {
        $orderStatus = $order->getPayment()->getMethodInstance()->getConfigData('order_status');
        $states = $order->getConfig()->getStateStatuses($orderState);

        if (!$orderStatus || !array_key_exists($orderStatus, $states)) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
        }

        return $orderStatus;
    }
}
