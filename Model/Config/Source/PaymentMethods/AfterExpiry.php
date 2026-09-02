<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source\PaymentMethods;

use Magento\Framework\Data\OptionSourceInterface;

class AfterExpiry implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'amex',                   'label' => __('American Express')],
            ['value' => 'bancontactmrcash',       'label' => __('Bancontact')],
            ['value' => 'transfer',               'label' => __('Bank Transfer')],
            ['value' => 'cartebancaire',          'label' => __('Carte Bancaire')],
            ['value' => 'cartebleuevisa',         'label' => __('Carte Bleue')],
            ['value' => 'dankort',                'label' => __('Dankort')],
            ['value' => 'eps',                    'label' => __('EPS')],
            ['value' => 'giftcard',               'label' => __('Giftcards')],
            ['value' => 'ideal',                  'label' => __('iDEAL')],
            ['value' => 'maestro',                'label' => __('Maestro')],
            ['value' => 'mastercard',             'label' => __('Mastercard')],
            ['value' => 'nexi',                   'label' => __('Nexi')],
            ['value' => 'postepay',               'label' => __('PostePay')],
            ['value' => 'paypal',                 'label' => __('PayPal')],
            ['value' => 'belfius',                'label' => __('Belfius')],
            ['value' => 'visa',                   'label' => __('Visa')],
            ['value' => 'visaelectron',           'label' => __('Visa Electron')],
            ['value' => 'vpay',                   'label' => __('V PAY')],
            ['value' => 'alipay',                 'label' => __('Alipay')],
            ['value' => 'wechatpay',              'label' => __('WeChatPay')],
            ['value' => 'p24',                    'label' => __('P24')],
            ['value' => 'trustly',                'label' => __('Trustly')],
            ['value' => 'blik',                   'label' => __('Blik')]
        ];
    }
}
