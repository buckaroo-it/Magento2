<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\PaymentMethodDataBuilder;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentMethodDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|BuckarooAdapter
     */
    private $buckarooAdapterMock;

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

        $this->buckarooAdapterMock = $this->getMockBuilder(BuckarooAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentMethodDataBuilder = new PaymentMethodDataBuilder();
    }

    /**
     * @dataProvider buildDataProvider
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
    public function buildDataProvider(): array
    {
        return [
            [
                'payment_method' => 'buckaroo_magento2_ideal',
                'expected_results' => 'ideal'
            ],
            [
                'payment_method' => 'buckaroo_magento2_creditcard',
                'expected_results' => 'creditcard'
            ],
            [
                'payment_method' => 'paypal',
                'expected_results' => 'paypal'
            ],
        ];
    }


}