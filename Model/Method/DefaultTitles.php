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

namespace Buckaroo\Magento2\Model\Method;

class DefaultTitles
{
    protected static array $labels = [
        'afterpay' => 'Riverty | Afterpay',
        'afterpay2' => 'Riverty | Afterpay',
        'afterpay20' => 'Riverty | Afterpay',
        'billink' => 'Billink',
        'payperemail' => 'PayPerEmail',
        'paylink' => 'PayLink',
        'creditcard' => 'Credit or Debit card',
        'creditcards' => 'Credit or Debit card',
        'ideal' => 'iDEAL',
        'idealprocessing' => 'iDEAL',
        'mrcash' => 'Buckaroo Bancontact',
        'paypal' => 'PayPal',
        'payconiq' => 'Payconiq',
        'sepadirectdebit' => 'SEPA Direct Debit',
        'sofortbanking' => 'SOFORT',
        'belfius' => 'Belfius',
        'transfer' => 'Bank Transfer',
        'eps' => 'EPS',
        'giropay' => 'Giropay',
        'kbc' => 'KBC',
        'klarna' => 'Klarna: Pay Later',
        'klarnakp' => 'Klarna: Pay later (authorize/capture)',
        'klarnain' => 'Klarna: Slice it',
        'applepay' => 'Apple Pay',
        'capayablein3' => 'iDEAL In3',
        'capayablepostpay' => 'In3',
        'alipay' => 'Alipay',
        'wechatpay' => 'WeChat Pay',
        'p24' => 'Przelewy24',
        'trustly' => 'Trustly',
        'pospayment' => 'PosPay',
        'tinka' => 'Tinka',
        'paybybank' => 'PayByBank'
    ];

    public static function get(string $paymentCode): string
    {
        if (isset(self::$labels[$paymentCode])) {
            return self::$labels[$paymentCode];
        }
        return $paymentCode;
    }
}
