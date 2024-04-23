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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class CapayableIn3 extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_capayablein3';

    public const DEFAULT_NAME = 'iDEAL In3';
    public const V2_NAME = 'In3';

    const XPATH_CAPAYABLEIN3_API_VERSION  = 'api_version';
    const XPATH_CAPAYABLEIN3_PAYMENT_LOGO = 'payment_logo';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @var array
     */
    protected $allowedCountries = [
        'NL'
    ];

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'showFinancialWarning' => $this->canShowFinancialWarning(),
        ]);
    }

    /**
     * Get Logo based on API version
     *
     * @param $storeId
     * @return string
     */
    public function getLogo($storeId = null): string
    {
        $logo = 'ideal-in3.svg';

        if ($this->isV2($storeId)) {
            $logo = 'in3.svg';
        }

        return $this->logoService->getLogoUrl("images/svg/".$logo);
    }

    /**
     * Check if API Version is V2
     *
     * @param $storeId
     * @return bool
     */
    public function isV2($storeId = null): bool
    {
        return $this->getMethodConfigValue(self::XPATH_CAPAYABLEIN3_API_VERSION, $storeId) === 'V2';
    }
}
