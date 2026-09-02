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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\PaymentMethodDataBuilder;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class PaymentMethodDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var PaymentMethodDataBuilder
     */
    private $paymentMethodDataBuilder;

    /**
     * @var PaymentGroupTransaction|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentGroupTransaction;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentGroupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $this->paymentMethodDataBuilder = new PaymentMethodDataBuilder($this->paymentGroupTransaction);
    }

    /**
     *
     * @param string $payment_method
     * @param string $expectedResult
     */
    #[DataProvider('buildDataProvider')]
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
