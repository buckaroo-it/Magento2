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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as MethodFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider as MethodAbstractConfigProvider;

class Account extends AbstractConfigProvider
{
    /**
     * XPATHs to configuration values for buckaroo_magento2_account
     */
    public const XPATH_ACCOUNT_ACTIVE                          = 'buckaroo_magento2/account/active';
    public const XPATH_ACCOUNT_SECRET_KEY                      = 'buckaroo_magento2/account/secret_key';
    public const XPATH_ACCOUNT_MERCHANT_KEY                    = 'buckaroo_magento2/account/merchant_key';
    public const XPATH_ACCOUNT_TRANSACTION_LABEL               = 'buckaroo_magento2/account/transaction_label';
    public const XPATH_ACCOUNT_INVOICE_HANDLING                = 'buckaroo_magento2/account/invoice_handling';
    public const XPATH_ACCOUNT_REFUND_LABEL                    = 'buckaroo_magento2/account/refund_label';
    public const XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL        = 'buckaroo_magento2/account/order_confirmation_email';
    public const XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL_SYNC   =
        'buckaroo_magento2/account/order_confirmation_email_sync';
    public const XPATH_ACCOUNT_INVOICE_EMAIL                   = 'buckaroo_magento2/account/invoice_email';
    public const XPATH_ACCOUNT_SUCCESS_REDIRECT                = 'buckaroo_magento2/account/success_redirect';
    public const XPATH_ACCOUNT_FAILURE_REDIRECT                = 'buckaroo_magento2/account/failure_redirect';
    public const XPATH_ACCOUNT_FAILURE_REDIRECT_TO_CHECKOUT    =
        'buckaroo_magento2/account/failure_redirect_to_checkout';
    public const XPATH_ACCOUNT_CANCEL_ON_FAILED                = 'buckaroo_magento2/account/cancel_on_failed';
    public const XPATH_ACCOUNT_CANCEL_ON_BROWSER_BACK          = 'buckaroo_magento2/account/cancel_on_browser_back';
    public const XPATH_ACCOUNT_LOG_LEVEL                       = 'buckaroo_magento2/account/debug_types';
    public const XPATH_ACCOUNT_LOG_HANDLER                     = 'buckaroo_magento2/account/log_handler';
    public const XPATH_ACCOUNT_LOG_DBTRACE_DEPTH               = 'buckaroo_magento2/account/log_handler_db_depth';
    public const XPATH_ACCOUNT_LOG_RETENTION                   = 'buckaroo_magento2/account/log_retention';
    public const XPATH_ACCOUNT_PAYMENT_FEE_LABEL               = 'buckaroo_magento2/account/payment_fee_label';
    public const XPATH_ACCOUNT_ORDER_STATUS_NEW                = 'buckaroo_magento2/account/order_status_new';
    public const XPATH_ACCOUNT_ORDER_STATUS_PENDING            = 'buckaroo_magento2/account/order_status_pending';
    public const XPATH_ACCOUNT_ORDER_STATUS_SUCCESS            = 'buckaroo_magento2/account/order_status_success';
    public const XPATH_ACCOUNT_ORDER_STATUS_FAILED             = 'buckaroo_magento2/account/order_status_failed';
    public const XPATH_ACCOUNT_CREATE_ORDER_BEFORE_TRANSACTION =
        'buckaroo_magento2/account/create_order_before_transaction';
    public const XPATH_ACCOUNT_IP_HEADER                       = 'buckaroo_magento2/account/ip_header';
    public const XPATH_ACCOUNT_CART_KEEP_ALIVE                 = 'buckaroo_magento2/account/cart_keep_alive';
    public const XPATH_ACCOUNT_CUSTOMER_ADDITIONAL_INFO        = 'buckaroo_magento2/account/customer_additional_info';

    public const XPATH_ACCOUNT_IDIN                            = 'buckaroo_magento2/account/idin';
    public const XPATH_ACCOUNT_IDIN_MODE                       = 'buckaroo_magento2/account/idin_mode';
    public const XPATH_ACCOUNT_IDIN_CATEGORY                   = 'buckaroo_magento2/account/idin_category';
    public const XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS          = 'buckaroo_magento2/account/buckaroo_fee_tax_class';

    /**
     * @var MethodFactory
     */
    protected $methodConfigProviderFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param MethodFactory        $methodConfigProviderFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        MethodFactory $methodConfigProviderFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($scopeConfig);

        $this->methodConfigProviderFactory = $methodConfigProviderFactory;
    }

    /**
     * @inheritdoc
     *
     * @throws BuckarooException
     */
    public function getConfig($store = null): array
    {
        return [
            'active'                            => $this->getActive($store),
            'secret_key'                        => $this->getSecretKey($store),
            'merchant_key'                      => $this->getMerchantKey($store),
            'transaction_label'                 => $this->getTransactionLabel($store),
            'order_confirmation_email'          => $this->getOrderConfirmationEmail($store),
            'order_confirmation_email_sync'     => $this->getOrderConfirmationEmailSync($store),
            'invoice_email'                     => $this->getInvoiceEmail($store),
            'success_redirect'                  => $this->getSuccessRedirect($store),
            'failure_redirect'                  => $this->getFailureRedirect($store),
            'failure_redirect_to_checkout'      => $this->getFailureRedirectToCheckout($store),
            'cancel_on_failed'                  => $this->getCancelOnFailed($store),
            'cancel_on_browser_back'            => $this->getCancelOnBrowserBack($store),
            'debug_types'                       => $this->getLogLevel($store),
            'log_handler'                       => $this->getLogHandler($store),
            'log_retention'                     => $this->getLogRetention($store),
            'payment_fee_label'                 => $this->getPaymentFeeLabel($store),
            'order_status_new'                  => $this->getOrderStatusNew($store),
            'order_status_pending'              => $this->getOrderStatusPending($store),
            'order_status_success'              => $this->getOrderStatusSuccess($store),
            'order_status_failed'               => $this->getOrderStatusFailed($store),
            'create_order_before_transaction'   => $this->getCreateOrderBeforeTransaction($store),
            'ip_header'                         => $this->getIpHeader($store),
            'cart_keep_alive'                   => $this->getCartKeepAlive($store),
            'buckaroo_fee_tax_class'            => $this->getBuckarooFeeTaxClass(),
            'customer_additional_info'          => $this->getCustomerAdditionalInfo($store),
            'idin'                              => $this->getIdin($store),
            'idin_mode'                         => $this->getIdinMode($store),
            'idin_category'                     => $this->getIdinCategory($store)
        ];
    }

    /**
     * Returns the method specific order status when available, or returns the global order status when not.
     *
     * @param string|null $paymentMethod
     *
     * @throws BuckarooException
     *
     * @return string
     */
    public function getOrderStatusSuccess($paymentMethod = null)
    {
        $orderStatusSuccess = $this->getAccountOrderStatusSuccess();

        /**
         * If a Payment Method is set, get the payment method status
         */
        if ($paymentMethod !== null) {
            /**
             * @var MethodAbstractConfigProvider $methodConfigProvider
             */
            $methodConfigProvider = $this->getMethodConfigProvider($paymentMethod);

            $activeStatus = $methodConfigProvider->getActiveStatus();
            $methodOrderStatus = $methodConfigProvider->getOrderStatusSuccess();

            if ($activeStatus && $methodOrderStatus !== null) {
                $orderStatusSuccess = $methodConfigProvider->getOrderStatusSuccess();
            }
        }
        return $orderStatusSuccess;
    }

    /**
     * Returns the method specific order status when available, or returns the global order status when not.
     *
     * @param string|null $paymentMethod
     *
     * @throws BuckarooException
     *
     * @return string
     */
    public function getOrderStatusFailed($paymentMethod = null)
    {
        $orderStatusFailed = $this->getAccountOrderStatusFailed();

        /**
         * If a Payment Method is set, get the payment method status
         */
        if ($paymentMethod !== null) {
            /**
             * @var MethodAbstractConfigProvider $methodConfigProvider
             */
            $methodConfigProvider = $this->getMethodConfigProvider($paymentMethod);

            $activeStatus = $methodConfigProvider->getActiveStatus();
            $methodOrderStatus = $methodConfigProvider->getOrderStatusFailed();

            if ($activeStatus && $methodOrderStatus !== null) {
                $orderStatusFailed = $methodConfigProvider->getOrderStatusFailed();
            }
        }
        return $orderStatusFailed;
    }

    /**
     * Gets the config provider for the given payment method.
     *
     * @param string $paymentMethod
     *
     * @throws BuckarooException
     *
     * @return Method\ConfigProviderInterface
     */
    protected function getMethodConfigProvider($paymentMethod)
    {
        $array = explode('_', $paymentMethod);
        $methodCode = $array[2];

        return $this->methodConfigProviderFactory->get($methodCode);
    }

    /**
     * Get the parsed label, we replace the template variables with the values
     *
     * @param Store          $store
     * @param OrderInterface $order
     * @param string|null    $label
     *
     * @return string
     */
    public function getParsedLabel(Store $store, OrderInterface $order, ?string $label = null)
    {
        if ($label === null) {
            $label = $this->getTransactionLabel($store);
        }

        if ($label === null) {
            return $store->getName();
        }

        $label = str_replace('{order_number}', $order->getIncrementId(), $label);
        $label = str_replace('{shop_name}', $order->getIncrementId(), $label);

        $products = $order->getItems();
        if (count($products)) {
            $label = str_replace('{product_name}', array_values($products)[0]->getName(), $label);
        }
        return mb_substr($label, 0, 244);
    }

    /**
     * Get active. Enable or disable the Buckaroo module.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Secret Key from Buckaroo Payment Engine
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getSecretKey($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_SECRET_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Merchant Store Key from Buckaroo Payment Engine
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getMerchantKey($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_MERCHANT_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the parsed label, we replace the template variables with the values
     *
     * @param Store          $store
     * @param OrderInterface $order
     *
     * @return mixed
     */
    public function getParsedRefundLabel(Store $store, OrderInterface $order)
    {
        $refundLabel = $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_REFUND_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return $this->getParsedLabel($store, $order, $refundLabel);
    }

    /**
     * Get transaction label
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getTransactionLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_TRANSACTION_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Create Invoice on Payment or on Shipment
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getInvoiceHandling($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_INVOICE_HANDLING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Should send a mail after successful creating the order.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getOrderConfirmationEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Send order confirmation email in sync mode
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getOrderConfirmationEmailSync($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL_SYNC,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Send a mail after successful payment.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getInvoiceEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_INVOICE_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Redirect after successful payments
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getSuccessRedirect($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_SUCCESS_REDIRECT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Redirect after failed payments.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getFailureRedirect($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_FAILURE_REDIRECT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Redirect after failed payments.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getFailureRedirectToCheckout($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_FAILURE_REDIRECT_TO_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Orders will stay open after failed payments.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCancelOnFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_CANCEL_ON_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Cancel order on browser back button.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCancelOnBrowserBack($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_CANCEL_ON_BROWSER_BACK,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Log level
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getLogLevel($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_LOG_LEVEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Log Handler (File/Database)
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getLogHandler($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_LOG_HANDLER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Debug backtrace logging depth
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getLogDbtraceDepth($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_LOG_DBTRACE_DEPTH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Log retention period
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getLogRetention($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_LOG_RETENTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Payment fee frontend label
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the status that will be given to new orders
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getOrderStatusNew($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_STATUS_NEW,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the status that will be given to orders paid
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getAccountOrderStatusSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_STATUS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the status that will be given to unsuccessful orders
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getAccountOrderStatusFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the status that will be given to orders pending payment
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getOrderStatusPending($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_ORDER_STATUS_PENDING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Create order before transaction
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCreateOrderBeforeTransaction($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_CREATE_ORDER_BEFORE_TRANSACTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Client IP detection headers (X-Forwarded-For,CF-Connecting-IP)
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getIpHeader($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_IP_HEADER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if the cart should be restored when consumer use back button
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCartKeepAlive($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_CART_KEEP_ALIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Add customer data to request
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCustomerAdditionalInfo($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_CUSTOMER_ADDITIONAL_INFO,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Enabled iDIN verification
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getIdin($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_IDIN,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get iDIN mode
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getIdinMode($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_IDIN_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get iDIN category
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getIdinCategory($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_IDIN_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Buckaroo fee tax class
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getBuckarooFeeTaxClass($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
