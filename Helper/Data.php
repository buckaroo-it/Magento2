<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\Business;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\GroupTransaction;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Config\Model\Config\ScopeDefiner;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Data extends AbstractHelper
{
    public const MODE_INACTIVE = 0;
    public const MODE_TEST     = 1;
    public const MODE_LIVE     = 2;

    public const M2_ORDER_STATE_PENDING = 'pending';

    /**
     * @var Account
     */
    public $configProviderAccount;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var CheckPaymentType
     */
    public $checkPaymentType;

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

    /**
     * @var array
     */
    protected $debugConfig = [];

    /**
     * @var Header
     */
    protected $httpHeader;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeDefiner
     */
    private $scopeDefiner;

    /**
     * @var State
     */
    private $state;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @param Context $context
     * @param Account $configProviderAccount
     * @param Factory $configProviderMethodFactory
     * @param Header $httpHeader
     * @param CheckoutSession $checkoutSession
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooLoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeDefiner $scopeDefiner
     * @param Json $json
     * @param State $state
     * @param CustomerSession $customerSession
     * @param CheckPaymentType $checkPaymentType
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Factory $configProviderMethodFactory,
        Header $httpHeader,
        CheckoutSession $checkoutSession,
        PaymentGroupTransaction $groupTransaction,
        BuckarooLoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        ScopeDefiner $scopeDefiner,
        Json $json,
        State $state,
        CustomerSession $customerSession,
        CheckPaymentType $checkPaymentType
    ) {
        parent::__construct($context);

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->httpHeader = $httpHeader;
        $this->checkoutSession = $checkoutSession;
        $this->groupTransaction = $groupTransaction;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->scopeDefiner = $scopeDefiner;
        $this->json = $json;
        $this->state = $state;
        $this->customerSession = $customerSession;
        $this->checkPaymentType = $checkPaymentType;
    }

    /**
     * Return the requested status $code, or null if not found
     *
     * @param string $code
     * @return int|null
     */
    public function getStatusCode(string $code): ?int
    {
        if (isset($this->statusCodes[$code])) {
            return $this->statusCodes[$code];
        }
        return null;
    }

    /**
     * Return the requested status key with the value, or null if not found
     *
     * @param int|string $value
     * @return false|int|string|null
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
    public function getStatusCodes(): array
    {
        return $this->statusCodes;
    }

    /**
     * Returns the additional transaction information
     *
     * @param array $array
     * @param array $rawInfo
     * @param string $keyPrefix
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array, array $rawInfo = [], string $keyPrefix = ''): array
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
     * Get the active mode for the given payment method and store.
     *
     * @param string|null $paymentMethod
     * @param string|int|null $store
     * @return mixed
     *
     * @throws BuckarooException
     */
    public function getMode(?string $paymentMethod = null, $store = null): int
    {
        $baseMode = $this->configProviderAccount->getActive();

        if (!$paymentMethod || !$baseMode) {
            return $baseMode;
        }

        /**
         * @var AbstractConfigProvider $configProvider
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
     * @return bool
     */
    public function isMobile(): bool
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        return preg_match(
            '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',
            $userAgent
        ) || preg_match(
            '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
            substr($userAgent, 0, 4)
        );
    }

    /**
     * Get original transaction key
     *
     * @param string|int $orderId
     * @return string|null
     */
    public function getOriginalTransactionKey($orderId): ?string
    {
        return $this->groupTransaction->getGroupTransactionOriginalTransactionKey($orderId);
    }

    /**
     * Check if the transaction is part of a group transaction
     *
     * @return bool
     */
    public function isGroupTransaction(): bool
    {
        return $this->groupTransaction->isGroupTransaction($this->getOrderId());
    }

    /**
     * Retrieve the reserved order ID for the current quote
     *
     * If the order ID is not yet reserved, reserve it now and save the quote.
     *
     * @return string
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getOrderId()
    {
        $orderId = $this->checkoutSession->getQuote()->getReservedOrderId();
        if (!$orderId) {
            $orderId = $this->checkoutSession->getQuote()->reserveOrderId()->getReservedOrderId();
            $this->checkoutSession->getQuote()->save();
        }
        return $orderId;
    }

    /**
     * Returns the current quote object.
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get current store
     *
     * @return StoreInterface|null
     */
    public function getStore(): ?StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (\Exception $e) {
            $this->logger->addError(
                '[Helper] | [Helper] | [' . __METHOD__ . ':' . __LINE__ . '] - Get Store | [ERROR]: ' . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Retrieve the sort order for the available gift card types
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfigGiftCardsSort()
    {
        return $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_giftcards/sorted_giftcards',
            $this->scopeDefiner->getScope(),
            ($this->scopeDefiner->getScope() == ScopeInterface::SCOPE_WEBSITES) ?
                $this->storeManager->getStore() :
                null
        );
    }

    /**
     * Check if two amounts are equal within a reasonable margin of error.
     *
     * @param float $amount1
     * @param float $amount2
     * @return bool
     */
    public function areEqualAmounts($amount1, $amount2): bool
    {
        if ($amount2 == 0) {
            return $amount1 == $amount2;
        } else {
            return abs((floatval($amount1) - floatval($amount2)) / floatval($amount2)) < 0.00001;
        }
    }

    /**
     * Returns the current checkout session object.
     *
     * @return CheckoutSession
     */
    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * Returns an instance of the JSON object.
     *
     * @return Json
     */
    public function getJson()
    {
        return $this->json;
    }

    /**
     * Get payment methods
     *
     * @return array[]
     */
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
            ['value' => 'creditcards',       'label' => __('Credit and debit cards (Hosted Fields)')],
            ['value' => 'eps',       'label' => __('EPS')],
            ['value' => 'giftcards',       'label' => __('Giftcards')],
            ['value' => 'ideal',       'label' => __('iDEAL')],
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
            ['value' => 'blik',       'label' => __('Blik')]
        ];
    }

    /**
     * Check if customer group is allowed for the payment method
     *
     * @param string $paymentMethod
     * @param bool $forceB2C
     * @return bool
     * @throws BuckarooException
     * @throws LocalizedException
     */
    public function checkCustomerGroup(string $paymentMethod, bool $forceB2C = false): bool
    {
        if (!$this->checkPaymentType->isBuckarooMethod($paymentMethod)) {
            return true;
        }

        $paymentMethodCode = $this->getBuckarooMethod($paymentMethod);
        $configProvider = $this->configProviderMethodFactory->get($paymentMethodCode);

        $configCustomerGroup = $this->determineCustomerGroup($paymentMethodCode, $configProvider, $forceB2C);

        if ($configCustomerGroup === null || $configCustomerGroup == Group::CUST_GROUP_ALL) {
            return true;
        }

        if ($configCustomerGroup == -1) {
            return false;
        }

        $configCustomerGroupArr = explode(',', $configCustomerGroup);

        return $this->checkCustomerGroupArea($configCustomerGroupArr);
    }

    /**
     * Determine the appropriate customer group configuration.
     *
     * @param string $paymentMethodCode
     * @param ConfigProviderInterface $configProvider
     * @param bool $forceB2C
     * @return int|null
     */
    private function determineCustomerGroup(
        string $paymentMethodCode,
        ConfigProviderInterface $configProvider,
        bool $forceB2C
    ): ?int {
        if (!$forceB2C && $this->isSpecialPaymentMethod($paymentMethodCode, $configProvider)) {
            return $configProvider->getSpecificCustomerGroupB2B();
        }

        return $configProvider->getSpecificCustomerGroup();
    }

    /**
     * Check if the payment method is one of the special cases.
     *
     * @param string $paymentMethodCode
     * @param ConfigProviderInterface $configProvider
     * @return bool
     */
    private function isSpecialPaymentMethod(string $paymentMethodCode, ConfigProviderInterface $configProvider): bool
    {
        return ($paymentMethodCode == 'billink')
            || (($paymentMethodCode == 'afterpay' || $paymentMethodCode == 'afterpay2')
                && $configProvider->getBusiness() == Business::BUSINESS_B2B)
            || ($paymentMethodCode == 'payperemail' && $configProvider->isEnabledB2B());
    }

    /**
     * Check if the customer group is allowed based on the area (admin or front).
     *
     * @param array $configCustomerGroupArr
     * @return bool
     * @throws LocalizedException
     */
    private function checkCustomerGroupArea(array $configCustomerGroupArr): bool
    {
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            return $this->checkCustomerGroupAdminArea($configCustomerGroupArr);
        } else {
            return $this->checkCustomerGroupFrontArea($configCustomerGroupArr);
        }
    }

    /**
     * Checks if a given payment method is a Buckaroo method
     *
     * @param string $paymentMethod
     * @return bool
     */
    public function isBuckarooMethod(string $paymentMethod): bool
    {
        return strpos($paymentMethod, 'buckaroo_magento2_') !== false;
    }

    /**
     * Extracts the Buckaroo payment method code from the full payment method code.
     *
     * @param string $paymentMethod
     * @return string
     */
    public function getBuckarooMethod(string $paymentMethod): string
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $paymentMethod));
    }

    /**
     * Get order status by state
     *
     * @param $order
     * @param $orderState
     * @return mixed
     */
    public function getOrderStatusByState($order, $orderState)
    {
        $orderStatus = $order->getPayment()->getMethodInstance()->getConfigData('order_status');
        $states = $order->getConfig()->getStateStatuses($orderState);

        if (!$orderStatus || !array_key_exists($orderStatus, $states)) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
        }

        return $orderStatus;
    }

    /**
     * Checks if the customer group in the admin area is allowed to use the Buckaroo payment method.
     *
     * @param array $configCustomerGroupArr
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function checkCustomerGroupAdminArea(array $configCustomerGroupArr): bool
    {
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ($customerId > 0)
            && ($customer = $this->customerRepository->getById($customerId))
            && $customerGroup = $customer->getGroupId()) {
                return in_array($customerGroup, $configCustomerGroupArr);
        }

        return true;
    }

    /**
     * Check if the current logged in customer's group matches with the allowed customer groups
     *
     * @param array $configCustomerGroupArr
     * @return bool
     */
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
}
