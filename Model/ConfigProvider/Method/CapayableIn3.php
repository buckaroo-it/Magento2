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

use Buckaroo\Magento2\Exception;
use Magento\Store\Model\ScopeInterface;

class CapayableIn3 extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_capayablein3';

    const XPATH_CAPAYABLEIN3_API_VERSION = 'payment/buckaroo_magento2_capayablein3/api_version';
    const XPATH_CAPAYABLEIN3_PAYMENT_LOGO = 'payment/buckaroo_magento2_capayablein3/payment_logo';


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
     *
     * @throws Exception
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'capayablein3' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'logo' => $this->getLogo()
                    ],
                ],
            ],
        ];
    }

    public function isV2($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_API_VERSION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === 'V2';
    }

    public function getLogo($storeId = null): string
    {
        $logo = $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_PAYMENT_LOGO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($this->isV2($storeId)) {
            return 'in3.svg';
        }

        if (!is_string($logo)) {
            return 'in3-ideal.svg';
        }

        return $logo;
    }
}
