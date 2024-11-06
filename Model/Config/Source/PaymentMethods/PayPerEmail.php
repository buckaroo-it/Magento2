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

namespace Buckaroo\Magento2\Model\Config\Source\PaymentMethods;

class PayPerEmail implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => 'amex',
                'label' => __('American Express'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'bancontactmrcash',
                'label' => __('Bancontact / Mr Cash'),
                'code' => 'buckaroo_magento2_mrcash'
            ],
            [
                'value' => 'transfer',
                'label' => __('Bank Transfer'),
                'code' => 'buckaroo_magento2_transfer'
            ],
            [
                'value' => 'cartebancaire',
                'label' => __('Carte Bancaire'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'cartebleuevisa',
                'label' => __('Carte Bleue'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'dankort',
                'label' => __('Dankort'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'eps',
                'label' => __('EPS'),
                'code' => 'buckaroo_magento2_eps'
            ],
            [
                'value' => 'giftcard',
                'label' => __('Giftcards'),
                'code' => 'buckaroo_magento2_giftcards'
            ],
            [
                'value' => 'ideal',
                'label' => __('iDEAL'),
                'code' => 'buckaroo_magento2_ideal'
            ],
            [
                'value' => 'idealprocessing',
                'label' => __('iDEAL Processing'),
                'code' => 'buckaroo_magento2_idealprocessing'
            ],
            [
                'value' => 'maestro',
                'label' => __('Maestro'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'mastercard',
                'label' => __('Mastercard'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'paypal',
                'label' => __('PayPal'),
                'code' => 'buckaroo_magento2_paypal'
            ],
            [
                'value' => 'sepadirectdebit',
                'label' => __('SEPA Direct Debit'),
                'code' => 'buckaroo_magento2_sepadirectdebit'
            ],
            [
                'value' => 'belfius',
                'label' => __('Belfius'),
                'code' => 'buckaroo_magento2_belfius'
            ],
            [
                'value' => 'visa',
                'label' => __('Visa'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'visaelectron',
                'label' => __('Visa Electron'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'vpay',
                'label' => __('V PAY'),
                'code' => 'buckaroo_magento2_creditcards'
            ],
            [
                'value' => 'alipay',
                'label' => __('Alipay'),
                'code' => 'buckaroo_magento2_alipay'
            ],
            [
                'value' => 'wechatpay',
                'label' => __('WeChatPay'),
                'code' => 'buckaroo_magento2_wechatpay'
            ],
            [
                'value' => 'p24',
                'label' => __('P24'),
                'code' => 'buckaroo_magento2_p24'
            ],
            [
                'value' => 'trustly',
                'label' => __('Trustly'),
                'code' => 'buckaroo_magento2_trustly'
            ],
            [
                'value' => 'blik',
                'label' => __('Blik'),
                'code' => 'buckaroo_magento2_blik'
            ]
        ];

        return $options;
    }
}
