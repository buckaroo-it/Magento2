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

    const M2_ORDER_STATE_PENDING = 'pending';

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
        'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL'      => 794,
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
            if (in_array($key, ['brq_websitekey', 'brq_signature', 'brq_payer_hash'])) {
                continue;
            }
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
        return preg_match(
            '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$userAgent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
            substr($userAgent,0,4)
        );
    }

    public function getOriginalTransactionKey($orderId)
    {
        return $this->groupTransaction->getGroupTransactionOriginalTransactionKey($orderId);
    }

    public function getAlreadyPaid()
    {
        return $this->groupTransaction->getAlreadyPaid($this->getOrderId());
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

    /**
     * Get current store
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    public function getStore()
    {
        try {
            return $this->storeManager->getStore();
        }
        catch (\Exception $e) {
            $this->logger->addDebug(__METHOD__.(string)$e);
            return null;
        }
    }

    public function getConfigCardSort()
    {
        $configValue = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_creditcard/sorted_issuers',
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
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ((int)$customerId > 0)) {
            $this->logger->addDebug(__METHOD__ . '|5|');
            if (!isset($this->staticCache['getPPeCustomerDetails'])
                && ($customer = $this->customerRepository->getById((int)$customerId))
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
                    'middleName' => $billingAddress ? $billingAddress->getMiddlename() : '',
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
                    'middleName' => $order['billing_address']['middlename'],
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
                'middleName' => $payment['customer_billingMiddleName'],
            ];
        }

        return $this->staticCache['getPPeCustomerDetails'] ?? null;
    }

    public function areEqualAmounts($amount1, $amount2)
    {
        if ($amount2 == 0) {
            return $amount1 == $amount2;
        } else {
            return abs((floatval($amount1) - floatval($amount2)) / floatval($amount2)) <= 0.01;
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
            ['value' => 'afterpay',               'label' => __('Riverty (old)')],
            ['value' => 'afterpay2',       'label' => __('Riverty 2 (old)')],
            ['value' => 'afterpay20',       'label' => __('Riverty')],
            ['value' => 'alipay',       'label' => __('Alipay')],
            ['value' => 'applepay',       'label' => __('Apple Pay')],
            ['value' => 'billink',       'label' => __('Billink')],
            ['value' => 'capayablein3',       'label' => __('In3')],
            ['value' => 'creditcard',       'label' => __('Credit and debit cards')],
            ['value' => 'creditcards',       'label' => __('Credit and debit cards (Client sided)')],
            ['value' => 'emandate',       'label' => __('Digital Debit Authorization')],
            ['value' => 'eps',       'label' => __('EPS')],
            ['value' => 'giftcards',       'label' => __('Giftcards')],
            ['value' => 'ideal',       'label' => __('iDEAL')],
            ['value' => 'idealprocessing',       'label' => __('iDEAL Processing')],
            ['value' => 'kbc',       'label' => __('KBC')],
            ['value' => 'klarna',       'label' => __('Klarna Pay later (pay)')],
            ['value' => 'klarnain',       'label' => __('Klarna Slice it')],
            ['value' => 'klarnakp',       'label' => __('Klarna Pay later (authorize/capture)')],
            ['value' => 'mrcash',       'label' => __('Bancontact')],
            ['value' => 'p24',       'label' => __('Przelewy24')],
            ['value' => 'payconiq',       'label' => __('Payconiq')],
            ['value' => 'paylink',       'label' => __('PayLink')],
            ['value' => 'paypal',       'label' => __('Paypal')],
            ['value' => 'payperemail',       'label' => __('PayPerEmail')],
            ['value' => 'pospayment',       'label' => __('Point of Sale')],
            ['value' => 'sepadirectdebit',       'label' => __('SEPA direct debit')],
            ['value' => 'belfius',       'label' => __('Belfius')],
            ['value' => 'transfer',       'label' => __('Bank Transfer')],
            ['value' => 'trustly',       'label' => __('Trustly')],
            ['value' => 'wechatpay',       'label' => __('WeChatPay')],
            ['value' => 'blik',       'label' => __('Blik')],
        ];
    }

    public function checkCustomerGroup(string $paymentMethod, bool $forceB2C = false): bool
    {
        if ($this->isBuckarooMethod($paymentMethod)) {
            $paymentMethodCode = $this->getBuckarooMethod($paymentMethod);
            $configProvider = $this->configProviderMethodFactory->get($paymentMethodCode);
            $configCustomerGroup = $configProvider->getSpecificCustomerGroup();

            if (!$forceB2C && $this->isBusinessCustomer($paymentMethodCode, $configProvider)) {
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

    private function isBusinessCustomer(string $paymentMethodCode, $configProvider): bool
    {
        return (
            ($paymentMethodCode == 'billink') ||
            (
                (($paymentMethodCode == 'afterpay') || ($paymentMethodCode == 'afterpay2')) &&
                ($configProvider->getBusiness() == Business::BUSINESS_B2B)
            ) ||
            (
                ($paymentMethodCode == 'payperemail') && ($configProvider->getEnabledB2B())
            )
        );
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
