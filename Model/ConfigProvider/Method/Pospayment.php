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

class Pospayment extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_pospayment';

    public const XPATH_POSPAYMENT_OTHER_PAYMENT_METHODS = 'payment/buckaroo_magento2_pospayment/other_payment_methods';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @inheritdoc
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
                    'pospayment' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ]
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getOtherPaymentMethods($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_POSPAYMENT_OTHER_PAYMENT_METHODS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
