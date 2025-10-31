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
