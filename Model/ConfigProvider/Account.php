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

namespace TIG\Buckaroo\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory as MethodFactory;

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
 * @method mixed getDigitalSignature()
 * @method mixed getDebugTypes()
 * @method mixed getDebugEmail()
 * @method mixed getLimitByIp()
 * @method mixed getFeePercentageMode()
 * @method mixed getOrderStatusPending()
 * @method mixed getOrderStatusNew()
 * @method mixed getPaymentFeeLabel()
 * @method mixed getCreateOrderBeforeTransaction()
 */
class Account extends AbstractConfigProvider
{
    /**
     * XPATHs to configuration values for tig_buckaroo_account
     */
    const XPATH_ACCOUNT_ACTIVE                          = 'tig_buckaroo/account/active';
    const XPATH_ACCOUNT_SECRET_KEY                      = 'tig_buckaroo/account/secret_key';
    const XPATH_ACCOUNT_MERCHANT_KEY                    = 'tig_buckaroo/account/merchant_key';
    const XPATH_ACCOUNT_MERCHANT_GUID                   = 'tig_buckaroo/account/merchant_guid';
    const XPATH_ACCOUNT_TRANSACTION_LABEL               = 'tig_buckaroo/account/transaction_label';
    const XPATH_ACCOUNT_CERTIFICATE_FILE                = 'tig_buckaroo/account/certificate_file';
    const XPATH_ACCOUNT_ORDER_CONFIRMATION_EMAIL        = 'tig_buckaroo/account/order_confirmation_email';
    const XPATH_ACCOUNT_INVOICE_EMAIL                   = 'tig_buckaroo/account/invoice_email';
    const XPATH_ACCOUNT_SUCCESS_REDIRECT                = 'tig_buckaroo/account/success_redirect';
    const XPATH_ACCOUNT_FAILURE_REDIRECT                = 'tig_buckaroo/account/failure_redirect';
    const XPATH_ACCOUNT_CANCEL_ON_FAILED                = 'tig_buckaroo/account/cancel_on_failed';
    const XPATH_ACCOUNT_DIGITAL_SIGNATURE               = 'tig_buckaroo/account/digital_signature';
    const XPATH_ACCOUNT_DEBUG_TYPES                     = 'tig_buckaroo/account/debug_types';
    const XPATH_ACCOUNT_DEBUG_EMAIL                     = 'tig_buckaroo/account/debug_email';
    const XPATH_ACCOUNT_LIMIT_BY_IP                     = 'tig_buckaroo/account/limit_by_ip';
    const XPATH_ACCOUNT_FEE_PERCENTAGE_MODE             = 'tig_buckaroo/account/fee_percentage_mode';
    const XPATH_ACCOUNT_PAYMENT_FEE_LABEL               = 'tig_buckaroo/account/payment_fee_label';
    const XPATH_ACCOUNT_ORDER_STATUS_NEW                = 'tig_buckaroo/account/order_status_new';
    const XPATH_ACCOUNT_ORDER_STATUS_PENDING            = 'tig_buckaroo/account/order_status_pending';
    const XPATH_ACCOUNT_ORDER_STATUS_SUCCESS            = 'tig_buckaroo/account/order_status_success';
    const XPATH_ACCOUNT_ORDER_STATUS_FAILED             = 'tig_buckaroo/account/order_status_failed';
    const XPATH_ACCOUNT_CREATE_ORDER_BEFORE_TRANSACTION = 'tig_buckaroo/account/create_order_before_transaction';

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
            'invoice_email'                     => $this->getInvoiceEmail($store),
            'success_redirect'                  => $this->getSuccessRedirect($store),
            'failure_redirect'                  => $this->getFailureRedirect($store),
            'cancel_on_failed'                  => $this->getCancelOnFailed($store),
            'digital_signature'                 => $this->getDigitalSignature($store),
            'debug_types'                       => $this->getDebugTypes($store),
            'debug_email'                       => $this->getDebugEmail($store),
            'limit_by_ip'                       => $this->getLimitByIp($store),
            'fee_percentage_mode'               => $this->getFeePercentageMode($store),
            'payment_fee_label'                 => $this->getPaymentFeeLabel($store),
            'order_status_new'                  => $this->getOrderStatusNew($store),
            'order_status_pending'              => $this->getOrderStatusPending($store),
            'order_status_success'              => $this->getOrderStatusSuccess($store),
            'order_status_failed'               => $this->getOrderStatusFailed($store),
            'create_order_before_transaction'   => $this->getCreateOrderBeforeTransaction($store),
        ];
        return $config;
    }

    /**
     * Returns the method specific order status when available, or returns the global order status when not.
     *
     * @param null $paymentMethod
     *
     * @return string
     * @throws \TIG\Buckaroo\Exception
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
             * @var \TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider $methodConfigProvider
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
     * @return string
     * @throws \TIG\Buckaroo\Exception
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
             * @var \TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider $methodConfigProvider
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
     * @return Method\ConfigProviderInterface
     * @throws \TIG\Buckaroo\Exception
     */
    protected function getMethodConfigProvider($paymentMethod)
    {
        $array = explode('_', $paymentMethod);
        $methodCode = $array[2];

        return $this->methodConfigProviderFactory->get($methodCode);
    }
}
