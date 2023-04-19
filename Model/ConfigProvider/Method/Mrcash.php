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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Mrcash extends AbstractConfigProvider
{
    const XPATH_MRCASH_PAYMENT_FEE              = 'payment/buckaroo_magento2_mrcash/payment_fee';
    const XPATH_MRCASH_PAYMENT_FEE_LABEL        = 'payment/buckaroo_magento2_mrcash/payment_fee_label';
    const XPATH_MRCASH_ACTIVE                   = 'payment/buckaroo_magento2_mrcash/active';
    const XPATH_MRCASH_SUBTEXT                  = 'payment/buckaroo_magento2_mrcash/subtext';
    const XPATH_MRCASH_SUBTEXT_STYLE            = 'payment/buckaroo_magento2_mrcash/subtext_style';
    const XPATH_MRCASH_SUBTEXT_COLOR            = 'payment/buckaroo_magento2_mrcash/subtext_color';
    const XPATH_MRCASH_ACTIVE_STATUS            = 'payment/buckaroo_magento2_mrcash/active_status';
    const XPATH_MRCASH_ORDER_STATUS_SUCCESS     = 'payment/buckaroo_magento2_mrcash/order_status_success';
    const XPATH_MRCASH_ORDER_STATUS_FAILED      = 'payment/buckaroo_magento2_mrcash/order_status_failed';
    const XPATH_MRCASH_AVAILABLE_IN_BACKEND     = 'payment/buckaroo_magento2_mrcash/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_mrcash/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_mrcash/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_mrcash/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_mrcash/specificcustomergroup';

    const XPATH_MRCASH_USE_CLIENT_SIDE          = 'payment/buckaroo_magento2_mrcash/client_side';

    const MRCASH_REDIRECT_URL = '/buckaroo/mrcash/pay';

    /** @var FormKey */
    private $formKey;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param FormKey              $formKey
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        FormKey $formKey
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->formKey = $formKey;
    }

    private function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Mrcash::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'mrcash' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'useClientSide' => (int) $this->useClientSide(),
                        'redirecturl' => self::MRCASH_REDIRECT_URL . '?form_key=' . $this->getFormKey()
                    ],
                ],
            ],
        ];
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_MRCASH_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * @return bool
     */
    private function useClientSide()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_MRCASH_USE_CLIENT_SIDE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
