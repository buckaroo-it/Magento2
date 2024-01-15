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

use Buckaroo\Magento2\Exception;

class Klarnain extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_klarnain';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
        'GBP',
        'DKK',
        'SEK',
        'NOK'
    ];

    /**
     * @var array
     */
    protected $allowedCountries = [
        'DE',
        'AT',
        'GB',
        'DK',
        'SE',
        'NO',
        'FI',
    ];

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarnain' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'paymentFee'        => $this->getPaymentFee(),
                        'genderList'        => [
                            ['genderType' => 'male', 'genderTitle' => __('He/him')],
                            ['genderType' => 'female', 'genderTitle' => __('She/her')]
                        ],
                        'showFinancialWarning' => $this->canShowFinancialWarning(),
                        'isTestMode'        => $this->isTestMode()
                    ],
                    'response' => [],
                ],
            ],
        ];
    }
}
