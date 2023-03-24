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
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Creditcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_creditcards';

    public const XPATH_CREDITCARDS_SELLERS_PROTECTION = 'payment/buckaroo_magento2_creditcards/sellers_protection';
    public const XPATH_CREDITCARDS_SELLERS_PROTECTION_ELIGIBLE =
        'payment/buckaroo_magento2_creditcards/sellers_protection_eligible';
    public const XPATH_CREDITCARDS_SELLERS_PROTECTION_INELIGIBLE =
        'payment/buckaroo_magento2_creditcards/sellers_protection_ineligible';
    public const XPATH_CREDITCARDS_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE =
        'payment/buckaroo_magento2_creditcards/sellers_protection_itemnotreceived_eligible';
    public const XPATH_CREDITCARDS_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE =
        'payment/buckaroo_magento2_creditcards/sellers_protection_unauthorizedpayment_eligible';
    public const XPATH_CREDITCARDS_ALLOWED_ISSUERS = 'payment/buckaroo_magento2_creditcards/allowed_creditcards';
    public const XPATH_USE_CARD_DESIGN = 'payment/buckaroo_magento2_creditcards/card_design';

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
     * @inheritDoc
     */
    public function getConfig(): array
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        $issuers = $this->formatIssuers();

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcards' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'creditcards' => $issuers,
                        'defaultCardImage' => $this->getImageUrl('svg/creditcards', 'svg'),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $issuers = parent::formatIssuers();
        $allowed = explode(',', (string)$this->getAllowedIssuers());

        $issuers = $this->issuers;

        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
            $issuers[$key]['img'] = $this->getCreditcardLogo($issuer['code']);
        }

        return $issuers;
    }

    /**
     * Get Sellers Protection
     */
    public function getSellersProtection($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Eligible
     */
    public function getSellersProtectionEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Ineligible
     */
    public function getSellersProtectionIneligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_INELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Itemnotreceived Eligible
     */
    public function getSellersProtectionItemnotreceivedEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Sellers Protection Unauthorizedpayment Eligible
     */
    public function getSellersProtectionUnauthorizedpaymentEligible($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Allowed Issuers
     */
    public function getAllowedIssuers($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Active Status Cm3
     */
    public function getActiveStatusCm3()
    {
        return null;
    }
}
