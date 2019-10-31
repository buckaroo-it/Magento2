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

namespace TIG\Buckaroo\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies;

/**
 * @method getPaymentFeeLabel()
 * @method getSellersProtection()
 * @method getSellersProtectionEligible()
 * @method getSellersProtectionIneligible()
 * @method getSellersProtectionItemnotreceivedEligible()
 * @method getSellersProtectionUnauthorizedpaymentEligible()
 */
class Creditcards extends AbstractConfigProvider
{
    const XPATH_CREDITCARDS_PAYMENT_FEE                      = 'payment/tig_buckaroo_creditcards/payment_fee';
    const XPATH_CREDITCARDS_PAYMENT_FEE_LABEL                = 'payment/tig_buckaroo_creditcards/payment_fee_label';
    const XPATH_CREDITCARDS_ACTIVE                           = 'payment/tig_buckaroo_creditcards/active';
    const XPATH_CREDITCARDS_ACTIVE_STATUS                    = 'payment/tig_buckaroo_creditcards/active_status';
    const XPATH_CREDITCARDS_ORDER_STATUS_SUCCESS             = 'payment/tig_buckaroo_creditcards/order_status_success';
    const XPATH_CREDITCARDS_ORDER_STATUS_FAILED              = 'payment/tig_buckaroo_creditcards/order_status_failed';
    const XPATH_CREDITCARDS_AVAILABLE_IN_BACKEND             = 'payment/tig_buckaroo_creditcards/available_in_backend';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION               = 'payment/tig_buckaroo_creditcards/sellers_protection';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_ELIGIBLE      = 'payment/tig_buckaroo_creditcards/sellers_protection_eligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_INELIGIBLE    = 'payment/tig_buckaroo_creditcards/sellers_protection_ineligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/tig_buckaroo_creditcards/sellers_protection_itemnotreceived_eligible';
    const XPATH_CREDITCARDS_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/tig_buckaroo_creditcards/sellers_protection_unauthorizedpayment_eligible';
    const XPATH_CREDITCARDS_ALLOWED_ISSUERS                  = 'payment/tig_buckaroo_creditcards/allowed_creditcards';
    const XPATH_USE_CARD_DESIGN                              = 'payment/tig_buckaroo_creditcards/card_design';
    const XPATH_ALLOWED_CURRENCIES                           = 'payment/tig_buckaroo_creditcards/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                               = 'payment/tig_buckaroo_creditcards/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                             = 'payment/tig_buckaroo_creditcards/specificcountry';

    /**
     * Creditcards constructor.
     *
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param Creditcard           $creditcardConfigProvider
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        Creditcard $creditcardConfigProvider
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->issuers = $creditcardConfigProvider->getIssuers();
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Creditcards::PAYMENT_METHOD_CODE);
        $issuers = $this->formatIssuers();

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcards' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'creditcards' => $issuers,
                        'defaultCardImage' => $this->getImageUrl('tig_buckaroo_creditcard_title'),
                        'useCardDesign' => $this->useCardDesign(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
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
            self::XPATH_CREDITCARDS_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $issuers = parent::formatIssuers();
        $allowed = explode(',', $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE
        ));

        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
        }

        return $issuers;
    }

    /**
     * @return bool
     */
    private function useCardDesign()
    {
        return $this->scopeConfig->getValue(self::XPATH_USE_CARD_DESIGN, ScopeInterface::SCOPE_STORE);
    }
}
