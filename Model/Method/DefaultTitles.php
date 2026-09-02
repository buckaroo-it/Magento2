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

namespace Buckaroo\Magento2\Model\Method;

class DefaultTitles
{
    /**
     * @var array
     */
    protected static $labels = [
        'afterpay'         => 'Riverty',
        'afterpay2'        => 'Riverty',
        'afterpay20'       => 'Riverty',
        'billink'          => 'Billink',
        'payperemail'      => 'PayPerEmail',
        'paylink'          => 'PayLink',
        'creditcard'       => 'Credit or Debit card',
        'creditcards'      => 'Credit or Debit card',
        'ideal'            => 'iDEAL',
        'mrcash'           => 'Bancontact',
        'paypal'           => 'PayPal',
        'sepadirectdebit'  => 'SEPA Direct Debit',
        'belfius'          => 'Belfius',
        'bizum'            => 'Bizum',
        'swish'            => 'Swish',
        'transfer'         => 'Bank Transfer',
        'eps'              => 'EPS',
        'kbc'              => 'KBC',
        'klarna'           => 'Klarna: Pay Later',
        'klarnakp'         => 'Klarna: Pay later (authorize/capture)',
        'applepay'         => 'Apple Pay',
        'capayablein3'     => 'In3',
        'capayablepostpay' => 'In3',
        'alipay'           => 'Alipay',
        'wechatpay'        => 'WeChat Pay',
        'p24'              => 'Przelewy24',
        'trustly'          => 'Trustly',
        'pospayment'       => 'PosPay',
        'paybybank'        => 'PayByBank',
        'wero'             => 'Wero'
    ];

    /**
     * Get the default title for the given payment method code.
     *
     * @param string $paymentCode
     * @return string
     */
    // phpcs:ignore Magento2.Functions.StaticFunction -- stateless constant map lookup, interception has no value
    public static function get(string $paymentCode): string
    {
        if (isset(self::$labels[$paymentCode])) {
            return self::$labels[$paymentCode];
        }
        return $paymentCode;
    }
}
