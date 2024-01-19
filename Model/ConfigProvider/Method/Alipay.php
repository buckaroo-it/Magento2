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

class Alipay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_alipay';

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'buckaroo' => [
                    'alipay' => [
                        'paymentFeeLabel'   => $this->getBuckarooPaymentFeeLabel(),
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'isTestMode'        => $this->isTestMode()
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function getBaseAllowedCurrencies(): array
    {
        return [
            'EUR',
            'USD',
            'JPY',
            'GBP',
            'CAD',
            'AUD',
            'SGD',
            'CHF',
            'SEK',
            'DKK',
            'NOK',
            'NZD',
            'THB',
            'HKD'
        ];
    }
}
