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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source\PayPerEmail;

use TIG\Buckaroo\Model\Config\Source\PaymentMethods\PayPerEmail;
use TIG\Buckaroo\Test\BaseTest;

class PayPerEmailTest extends BaseTest
{
    protected $instanceClass = PayPerEmail::class;

    /**
     * @return array
     */
    public function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 'amex',               'label' => 'American Express']
            ],
            [
                ['value' => 'eps',                'label' => 'EPS']
            ],
            [
                ['value' => 'sepadirectdebit',        'label' => 'SEPA Direct Debit']
            ],
            [
                ['value' => 'giftcard',           'label' => 'Giftcards']
            ],
            [
                ['value' => 'giropay',            'label' => 'Giropay']
            ],
            [
                ['value' => 'ideal',              'label' => 'iDEAL']
            ],
            [
                ['value' => 'idealprocessing',    'label' => 'iDEAL Processing']
            ],
            [
                ['value' => 'mastercard',         'label' => 'Mastercard']
            ],
            [
                ['value' => 'paypal',             'label' => 'PayPal']
            ],
            [
                ['value' => 'sofortueberweisung', 'label' => 'Sofort Banking']
            ],
            [
                ['value' => 'transfer',           'label' => 'Bank Transfer']
            ],
            [
                ['value' => 'visa',               'label' => 'Visa']
            ],
            [
                ['value' => 'maestro',            'label' => 'Maestro']
            ],
            [
                ['value' => 'visaelectron',       'label' => 'Visa Electron']
            ],
            [
                ['value' => 'vpay',               'label' => 'V PAY']
            ]
        ];
    }

    /**
     * @param $paymentOption
     *
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertContains($paymentOption, $result);
    }
}
