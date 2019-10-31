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
namespace TIG\Buckaroo\Test\Unit\Model\Validator;

use TIG\Buckaroo\Model\Validator\Push;
use TIG\Buckaroo\Test\BaseTest;

class PayPerEmailTest extends BaseTest
{
    protected $instanceClass = Push::class;

    public function testValidate()
    {
        $instance = $this->getInstance();
        $result = $instance->validate(null);
        $this->assertTrue($result);
    }

    /**
     * @return array
     */
    public function decodePushValueProvider()
    {
        return [
            'normal value' => [
                'brq_key_data',
                'some value',
                'some value'
            ],
            'enccoded value' => [
                'brq_timestamp',
                '14%3a19%3a53',
                '14:19:53'
            ],
            'payconiq_PayconiqAndroidUrl' => [
                'brq_SERVICE_payconiq_PayconiqAndroidUrl',
                'http://tig.nl',
                'http://tig.nl'
            ],
            'payconiq_PayconiqIosUrl' => [
                'brq_SERVICE_payconiq_PayconiqIosUrl',
                'http://tig.nl',
                'http://tig.nl'
            ],
            'payconiq_PayconiqUrl' => [
                'brq_SERVICE_payconiq_PayconiqUrl',
                'http://tig.nl',
                'http://tig.nl'
            ],
            'payconiq_QrUrl' => [
                'brq_SERVICE_payconiq_QrUrl',
                'http://tig.nl',
                'http://tig.nl'
            ],
            'masterpass_CustomerPhoneNumber' => [
                'brq_SERVICE_masterpass_CustomerPhoneNumber',
                '+31201122233',
                '+31201122233'
            ],
            'masterpass_ShippingRecipientPhoneNumber' => [
                'brq_SERVICE_masterpass_ShippingRecipientPhoneNumber',
                '+31644455566',
                '+31644455566'
            ],
            'InvoiceDate' => [
                'brq_InvoiceDate',
                '2017-12-11T00:00:00.0000000+01:00',
                '2017-12-11T00:00:00.0000000+01:00'

            ],
            'DueDate' => [
                'brq_DueDate',
                '2017-12-12T00:00:00.0000000+01:00',
                '2017-12-12T00:00:00.0000000+01:00'
            ],
            'PreviousStepDateTime' => [
                'brq_PreviousStepDateTime',
                '0001-01-01T00:00:00.0000000+01:00',
                '0001-01-01T00:00:00.0000000+01:00'
            ],
            'EventDateTime' => [
                'brq_EventDateTime',
                '2017-12-11T14:19:53.4688849+01:00',
                '2017-12-11T14:19:53.4688849+01:00'
            ],
        ];
    }

    /**
     * @param $brqKey
     * @param $brqValue
     * @param $expected
     *
     * @dataProvider decodePushValueProvider
     */
    public function testDecodePushValue($brqKey, $brqValue, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('decodePushValue', [$brqKey, $brqValue], $instance);
        $this->assertEquals($expected, $result);
    }
}
