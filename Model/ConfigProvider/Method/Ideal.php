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

        return $this->fullConfig([
            'banks'         => $this->formatIssuers(),
            'selectionType' => $this->getSelectionType(),
            'showIssuers'   => $this->canShowIssuers(),
        ]);
    }

    /**
     * Selection type radio checkbox or drop down
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSelectionType($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SELECTION_TYPE, $store);
    }

    /**
     * Can show issuer selection in checkout
     *
     * @param string|null $storeId
     *
     * @return boolean
     */
    public function canShowIssuers(string $storeId = null): bool
    {
        return $this->getMethodConfigValue(self::XPATH_SHOW_ISSUERS, $storeId) == 1;
    }

    /**
     * Get gateway setting ideal/idealprocessing
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
     * @param $storeId
     * @return string
     */
    public function getSortedIssuers($storeId = null): string
    {
        return $this->getMethodConfigValue(self::XPATH_SORTED_ISSUERS, $storeId) ?? '';
    }

    /**
     * Generate the url to the desired asset.
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
     * Retrieve the list of issuers.
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

    public function isIDealEnabled($store = null)
    {
        return $this->getActive();
    }

    public function getLogoColor($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_IDEAL_FAST_CHECKOUT_LOGO, $store);
    }
}
