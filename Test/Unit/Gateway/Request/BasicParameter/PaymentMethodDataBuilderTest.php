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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\PaymentMethodDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class PaymentMethodDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var PaymentMethodDataBuilder
     */
    private $paymentMethodDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodDataBuilder = new PaymentMethodDataBuilder();
    }

    /**
     * @dataProvider buildDataProvider
     * @param string $payment_method
     * @param string $expectedResult
     */
    public function testBuild(string $payment_method, string $expectedResult): void
    {
        $this->paymentMethodInstanceMock->method('getCode')->willReturn($payment_method);

        $result = $this->paymentMethodDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals(['payment_method' => $expectedResult], $result);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [
                'payment_method' => 'buckaroo_magento2_ideal',
                'expectedResult' => 'ideal'
            ],
            [
                'payment_method' => 'buckaroo_magento2_creditcard',
                'expectedResult' => 'creditcard'
            ],
            [
                'payment_method' => 'paypal',
                'expectedResult' => 'paypal'
            ],
        ];
    }
}
