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
            ['value' => 'bancontactmrcash',       'label' => __('Bancontact / Mr Cash')],
            ['value' => 'transfer',               'label' => __('Bank Transfer')],
            ['value' => 'cartebancaire',          'label' => __('Carte Bancaire')],
            ['value' => 'cartebleuevisa',         'label' => __('Carte Bleue')],
            ['value' => 'dankort',                'label' => __('Dankort')],
            ['value' => 'eps',                    'label' => __('EPS')],
            ['value' => 'giftcard',               'label' => __('Giftcards')],
            ['value' => 'giropay',                'label' => __('Giropay')],
            ['value' => 'ideal',                  'label' => __('iDEAL')],
            ['value' => 'idealprocessing',        'label' => __('iDEAL Processing')],
            ['value' => 'maestro',                'label' => __('Maestro')],
            ['value' => 'mastercard',             'label' => __('Mastercard')],
            ['value' => 'nexi',                   'label' => __('Nexi')],
            ['value' => 'postepay',               'label' => __('PostePay')],
            ['value' => 'paypal',                 'label' => __('PayPal')],
            ['value' => 'sofortueberweisung',     'label' => __('Sofort Banking')],
            ['value' => 'belfius',                'label' => __('Belfius')],
            ['value' => 'visa',                   'label' => __('Visa')],
            ['value' => 'visaelectron',           'label' => __('Visa Electron')],
            ['value' => 'vpay',                   'label' => __('V PAY')],
            ['value' => 'alipay',                 'label' => __('Alipay')],
            ['value' => 'wechatpay',              'label' => __('WeChatPay')],
            ['value' => 'p24',                    'label' => __('P24')],
            ['value' => 'trustly',                'label' => __('Trustly')],
            ['value' => 'tinka',                  'label' => __('Tinka')],
        ];
    }
}
