<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\InvoiceDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class InvoiceDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var InvoiceDataBuilder
     */
    private InvoiceDataBuilder $invoiceDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->invoiceDataBuilder = new InvoiceDataBuilder();
    }

    public function testBuild(): void
    {
        $this->orderMock->method('getIncrementId')->willReturn('100000001');

        $result = $this->invoiceDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals([
            'invoice' => '100000001',
            'order'   => '100000001'
        ], $result);
    }
}