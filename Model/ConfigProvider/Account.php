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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * @method mixed getActive()
 * @method mixed getSecretKey()
 * @method mixed getMerchantKey()
 * @method mixed getMerchantGuid()
 * @method mixed getTransactionLabel()
 * @method mixed getCertificateFile()
 * @method mixed getOrderConfirmationEmail()
 * @method mixed getInvoiceEmail()
 * @method mixed getSuccessRedirect()
 * @method mixed getFailureRedirect()
 * @method mixed getCancelOnFailed()
 * @method mixed getCancelOnBrowserBack()
 * @method mixed getDigitalSignature()
 * @method mixed getDebugTypes()
 * @method mixed getDebugEmail()
 * @method mixed getLimitByIp()
 * @method mixed getOrderStatusPending()
 * @method mixed getOrderStatusNew()
 * @method mixed getCreateOrderBeforeTransaction()
 * @method mixed getCustomerAdditionalInfo()
 * @method mixed getBuckarooFeeTaxClass()
 */
class Account extends AbstractConfigProvider
{
    /**
     * XPATHs to configuration values for buckaroo_magento2_account
     */
    public const XPATH_ACCOUNT_ACTIVE                          = 'buckaroo_magento2/account/active';
    public const XPATH_ACCOUNT_SECRET_KEY                      = 'buckaroo_magento2/account/secret_key';
    public const XPATH_ACCOUNT_MERCHANT_KEY                    = 'buckaroo_magento2/account/merchant_key';
    public const XPATH_ACCOUNT_MERCHANT_GUID                   = 'buckaroo_magento2/account/merchant_guid';
    public const XPATH_ACCOUNT_TRANSACTION_LABEL               = 'buckaroo_magento2/account/transaction_label';
    public const XPATH_ACCOUNT_INVOICE_HANDLING                = 'buckaroo_magento2/account/invoice_handling';
    public const XPATH_ACCOUNT_CERTIFICATE_FILE                = 'buckaroo_magento2/account/certificate_file';
    public const XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL        = 'buckaroo_magento2/account/order_confirmation_email';
    public const XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL_SYNC   = 'buckaroo_magento2/account/order_confirmation_email_sync';
    public const XPATH_ACCOUNT_INVOICE_EMAIL                   = 'buckaroo_magento2/account/invoice_email';
    public const XPATH_ACCOUNT_SUCCESS_REDIRECT                = 'buckaroo_magento2/account/success_redirect';
    public const XPATH_ACCOUNT_FAILURE_REDIRECT                = 'buckaroo_magento2/account/failure_redirect';
    public const XPATH_ACCOUNT_FAILURE_REDIRECT_TO_CHECKOUT    = 'buckaroo_magento2/account/failure_redirect_to_checkout';
    public const XPATH_ACCOUNT_CANCEL_ON_FAILED                = 'buckaroo_magento2/account/cancel_on_failed';
    public const XPATH_ACCOUNT_CANCEL_ON_BROWSER_BACK          = 'buckaroo_magento2/account/cancel_on_browser_back';
    public const XPATH_ACCOUNT_DIGITAL_SIGNATURE               = 'buckaroo_magento2/account/digital_signature';
    public const XPATH_ACCOUNT_DEBUG_TYPES                     = 'buckaroo_magento2/account/debug_types';
    public const XPATH_ACCOUNT_DEBUG_EMAIL                     = 'buckaroo_magento2/account/debug_email';
    public const XPATH_ACCOUNT_LIMIT_BY_IP                     = 'buckaroo_magento2/account/limit_by_ip';
    public const XPATH_ACCOUNT_ORDER_STATUS_NEW                = 'buckaroo_magento2/account/order_status_new';
    public const XPATH_ACCOUNT_ORDER_STATUS_PENDING            = 'buckaroo_magento2/account/order_status_pending';
    public const XPATH_ACCOUNT_ORDER_STATUS_SUCCESS            = 'buckaroo_magento2/account/order_status_success';
    public const XPATH_ACCOUNT_ORDER_STATUS_FAILED             = 'buckaroo_magento2/account/order_status_failed';
    public const XPATH_ACCOUNT_CREATE_ORDER_BEFORE_TRANSACTION = 'buckaroo_magento2/account/create_order_before_transaction';
    public const XPATH_ACCOUNT_IP_HEADER                       = 'buckaroo_magento2/account/ip_header';
    public const XPATH_ACCOUNT_CART_KEEP_ALIVE                 = 'buckaroo_magento2/account/cart_keep_alive';
    public const XPATH_ACCOUNT_SELECTION_TYPE                  = 'buckaroo_magento2/account/selection_type';
    public const XPATH_ACCOUNT_CUSTOMER_ADDITIONAL_INFO        = 'buckaroo_magento2/account/customer_additional_info';
    public const XPATH_ACCOUNT_IDIN                            = 'buckaroo_magento2/account/idin';
    public const XPATH_ACCOUNT_IDIN_MODE                       = 'buckaroo_magento2/account/idin_mode';
    public const XPATH_ACCOUNT_IDIN_CATEGORY                   = 'buckaroo_magento2/account/idin_category';
    public const XPATH_ACCOUNT_ADVANCED_EXPORT_GIFTCARDS       = 'buckaroo_magento2/account/advanced_export_giftcards';
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
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'active'                            => $this->getActive($store),
            'secret_key'                        => $this->getSecretKey($store),
            'merchant_key'                      => $this->getMerchantKey($store),
            'merchant_guid'                     => $this->getMerchantGuid($store),
            'transaction_label'                 => $this->getTransactionLabel($store),
            'certificate_file'                  => $this->getCertificateFile($store),
            'order_confirmation_email'          => $this->getOrderConfirmationEmail($store),
            'order_confirmation_email_sync'     => $this->getOrderConfirmationEmailSync($store),
            'invoice_email'                     => $this->getInvoiceEmail($store),
            'success_redirect'                  => $this->getSuccessRedirect($store),
            'failure_redirect'                  => $this->getFailureRedirect($store),
            'failure_redirect_to_checkout'      => $this->getFailureRedirectToCheckout($store),
            'cancel_on_failed'                  => $this->getCancelOnFailed($store),
            'cancel_on_browser_back'            => $this->getCancelOnBrowserBack($store),
            'digital_signature'                 => $this->getDigitalSignature($store),
            'debug_types'                       => $this->getDebugTypes($store),
            'debug_email'                       => $this->getDebugEmail($store),
            'limit_by_ip'                       => $this->getLimitByIp($store),
            'order_status_new'                  => $this->getOrderStatusNew($store),
            'order_status_pending'              => $this->getOrderStatusPending($store),
            'order_status_success'              => $this->getOrderStatusSuccess($store),
            'order_status_failed'               => $this->getOrderStatusFailed($store),
            'create_order_before_transaction'   => $this->getCreateOrderBeforeTransaction($store),
            'ip_header'                         => $this->getIpHeader($store),
            'cart_keep_alive'                   => true,
            'selection_type'                    => $this->getSelectionType($store),
            'customer_additional_info'          => $this->getCustomerAdditionalInfo($store),
            'buckaroo_fee_tax_class'            => $this->getBuckarooFeeTaxClass($store),
            'idin'                              => $this->getIdin($store),
            'idin_mode'                         => $this->getIdinMode($store),
            'idin_category'                     => $this->getIdinCategory($store),
            'advanced_export_giftcards'         => $this->getAdvancedExportGiftcards($store),
        ];
        return $config;
    }
    /**
     * Returns the method specific order status when available, or returns the global order status when not.
     *
     * @param null $paymentMethod
     *
     * @throws \Buckaroo\Magento2\Exception
     * @return string
     */
    public function getOrderStatusSuccess($paymentMethod = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $orderStatusSuccess = parent::getOrderStatusSuccess();

        /**
         * If a Payment Method is set, get the payment method status
         */
        if ($paymentMethod !== null) {
            /**
             * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider $methodConfigProvider
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
     * @param null $paymentMethod
     *
     * @throws \Buckaroo\Magento2\Exception
     * @return string
     */
    public function getOrderStatusFailed($paymentMethod = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $orderStatusFailed = parent::getOrderStatusFailed();

        /**
         * If a Payment Method is set, get the payment method status
         */
        if ($paymentMethod !== null) {
            /**
             * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider $methodConfigProvider
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
     * @param $paymentMethod
     *
     * @throws \Buckaroo\Magento2\Exception
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
     *
     * @return string
     */
    public function getParsedLabel(Store $store, OrderInterface $order)
    {
        $label = $this->getTransactionLabel($store);

        if ($label === null) {
            return $store->getName();
        }

        $label = preg_replace('/\{order_number\}/', $order->getIncrementId(), $label);
        $label = preg_replace('/\{shop_name\}/', $store->getName(), $label);

        $products = $order->getItems();
        if (count($products)) {
            $label = preg_replace('/\{product_name\}/', array_values($products)[0]->getName(), $label);
        }
        return mb_substr($label, 0, 244);
    }

    /**
     * Create Invoice on Payment or on Shipment
     *
     * @param  null|int|string $store
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
}
