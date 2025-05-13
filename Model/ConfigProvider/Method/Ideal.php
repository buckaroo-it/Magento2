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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\Ideal\IssuersService;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;

class Ideal extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_ideal';
    public const XPATH_SELECTION_TYPE   = 'selection_type';
    public const XPATH_SHOW_ISSUERS     = 'show_issuers';
    public const XPATH_GATEWAY_SETTINGS = 'gateway_settings';
    public const XPATH_SORTED_ISSUERS   = 'sorted_issuers';
    public const XPATH_IDEAL_PAYMENT_FEE           = 'payment/buckaroo_magento2_ideal/payment_fee';

    // Ideal Fast Checkout
    const XPATH_IDEAL_FAST_CHECKOUT_ENABLE = 'payment/buckaroo_magento2_ideal/ideal_fast_checkout';
    const XPATH_IDEAL_FAST_CHECKOUT_BUTTONS = 'payment/buckaroo_magento2_ideal/available_buttons';
    const XPATH_IDEAL_FAST_CHECKOUT_LOGO = 'payment/buckaroo_magento2_ideal/ideal_logo_colors';
    /**
     * @var IssuersService
     */
    protected IssuersService $issuersService;
    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param LogoService $logoService
     * @param IssuersService $issuersService
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        IssuersService $issuersService
    ) {
        $this->issuersService = $issuersService;

        parent::__construct(
            $assetRepo,
            $scopeConfig,
            $allowedCurrencies,
            $paymentFeeHelper,
            $logoService
        );
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig();
    }

    /**
     * Selection type radio checkbox or drop down
     * This method might be obsolete now for frontend config, keep if used elsewhere.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSelectionType($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SELECTION_TYPE, $store);
    }

    /**
     * Get gateway setting ideal/idealprocessing
     * Remains unchanged.
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getGatewaySettings($storeId = null): string
    {
        return $this->getMethodConfigValue(self::XPATH_GATEWAY_SETTINGS, $storeId) ??
            str_replace('buckaroo_magento2_', '', self::CODE);
    }

    /**
     * Generate the url to the desired asset.
     * Remains unchanged.
     *
     * @param string $imgName
     * @param string $extension
     *
     * @return string
     */
    public function getImageUrl($imgName, string $extension = 'png')
    {
        return parent::getImageUrl("ideal/{$imgName}", "svg");
    }

    /**
     * Get all issuers formatted for admin or potentially other uses.
     * Remains unchanged for now, although its primary use (via formatIssuers in getConfig) is removed.
     *
     * @return array
     */
    public function getAllIssuers(): array
    {
        $issuers = $this->getIssuers();
        $issuersPrepared = [];
        foreach ($issuers as $issuer) {
            $issuer['img'] = $issuer['logo'];
            $issuersPrepared[$issuer['code']] = $issuer;
        }

        return $issuersPrepared;
    }

    /**
     * Retrieve the list of issuers from the service.
     * Remains unchanged for now.
     *
     * @return array
     */
    public function getIssuers()
    {
        return array_map(function ($issuer) {
            $issuer['logo'] = $this->issuersService->getImageUrlByIssuerId($issuer['id']);
            return $issuer;
        }, $this->issuersService->get());
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_IDEAL_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }

    public function canShowButtonForPage($page, $store = null)
    {
        $buttons = $this->getExpressButtons($store);
        if ($buttons === null) {
            return false;
        }

        $pages = explode(",", $buttons);
        return in_array($page, $pages);
    }

    public function getExpressButtons($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_BUTTONS, $store);
    }

    public function isFastCheckoutEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_ENABLE, $store);
    }

    public function isIDealEnabled()
    {
        return $this->getActive();
    }

    public function getLogoColor($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_LOGO, $store);
    }
}
