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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\VoucherDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;

class VoucherDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var VoucherDataBuilder
     */
    private $voucherDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->voucherDataBuilder = new VoucherDataBuilder();
    }

    /**
     * @dataProvider buildDataProvider
     */
    public function testBuild(string $voucherCode, string $expectedResult): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('voucher_code')
            ->willReturn($voucherCode);

        $result = $this->voucherDataBuilder->build(['payment' => $paymentDOMock]);
        
        $this->assertEquals(['vouchercode' => $expectedResult], $result);
    }

    /**
     * Test that build throws exception when voucher code is missing
     */
    public function testBuildThrowsExceptionWhenVoucherCodeMissing(): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('voucher_code')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher code is required');

        $this->voucherDataBuilder->build(['payment' => $paymentDOMock]);
    }

    /**
     * Test that build throws exception when voucher code is empty
     */
    public function testBuildThrowsExceptionWhenVoucherCodeEmpty(): void
    {
        $paymentDOMock = $this->getPaymentDOMock();
        
        $paymentDOMock->getPayment()
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('voucher_code')
            ->willReturn('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher code is required');

        $this->voucherDataBuilder->build(['payment' => $paymentDOMock]);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            [
                'voucher_code' => 'TEST123',
                'expected_result' => 'TEST123'
            ],
            [
                'voucher_code' => 'GIFT456',
                'expected_result' => 'GIFT456'
            ],
            [
                'voucher_code' => 'VOUCHER789',
                'expected_result' => 'VOUCHER789'
            ],
        ];
    }
}
