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

use Buckaroo\Magento2\Gateway\Request\BasicParameter\InvoiceDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class InvoiceDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var InvoiceDataBuilder
     */
    private $invoiceDataBuilder;

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
