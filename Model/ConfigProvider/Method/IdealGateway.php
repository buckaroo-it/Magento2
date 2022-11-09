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

use Magento\Store\Model\ScopeInterface;

class IdealGateway extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_idealgateway';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * Return checkout config
     *
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            self::CODE
        );

        $selectionType = $this->scopeConfig->getValue(
            \Buckaroo\Magento2\Model\ConfigProvider\Account::XPATH_ACCOUNT_SELECTION_TYPE,
            ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'idealgateway' => [
                        'banks' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                    ],
                ],
            ],
        ];
    }
}
