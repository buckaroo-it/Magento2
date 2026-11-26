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

namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Pdf;

use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\Config\Source\Display\Type;
use Buckaroo\Magento2\Service\Sales\Pdf\BuckarooFee;
use Buckaroo\Magento2\Test\BaseTest;

class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * Fee excl. tax is 10.00 in every row.
     *
     * @return array
     */
    public static function getTotalsForDisplayProvider()
    {
        return [
            'incl. tax uses the stored incl. tax amount' => [
                Type::DISPLAY_TYPE_INCLUDING_TAX,
                12.10,
                0.50,
                [['amount' => '€12.10', 'label' => 'Payment fee:', 'font_size' => 7]],
            ],
            'incl. tax falls back to amount plus tax amount' => [
                Type::DISPLAY_TYPE_INCLUDING_TAX,
                null,
                1.50,
                [['amount' => '€11.50', 'label' => 'Payment fee:', 'font_size' => 7]],
            ],
            'excl. tax' => [
                Type::DISPLAY_TYPE_EXCLUDING_TAX,
                12.10,
                0.50,
                [['amount' => '€10.00', 'label' => 'Payment fee:', 'font_size' => 7]],
            ],
            'excl. and incl. tax' => [
                Type::DISPLAY_TYPE_BOTH,
                12.10,
                0.50,
                [
                    ['amount' => '€10.00', 'label' => 'Payment fee (Excl. Tax):', 'font_size' => 7],
                    ['amount' => '€12.10', 'label' => 'Payment fee (Incl. Tax):', 'font_size' => 7],
                ],
            ],
            'unknown display type shows excl. tax' => [
                0,
                12.10,
                0.50,
                [['amount' => '€10.00', 'label' => 'Payment fee:', 'font_size' => 7]],
            ],
        ];
    }

    /**
     * @param int        $displayType
     * @param float|null $feeInclTax
     * @param float      $feeTaxAmount
     * @param array      $expected
     */
    #[DataProvider('getTotalsForDisplayProvider')]
    public function testGetTotalsForDisplay($displayType, $feeInclTax, $feeTaxAmount, $expected)
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('tax/sales_display/buckaroo_fee', 'store', 5)
            ->willReturn((string)$displayType);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn('Payment fee:');

        $orderMock = $this->getFakeMock(Order::class)->onlyMethods(['getStoreId', 'formatPriceTxt'])->getMock();
        $orderMock->method('getStoreId')->willReturn(5);
        $orderMock->method('formatPriceTxt')->willReturnCallback(
            static function ($amount) {
                return '€' . number_format((float)$amount, 2);
            }
        );

        $invoiceMock = $this->getFakeMock(Order\Invoice::class)->onlyMethods([])->getMock();
        $invoiceMock->setData('buckaroo_fee', 10.00);
        $invoiceMock->setData('buckaroo_fee_incl_tax', $feeInclTax);
        $invoiceMock->setData('buckaroo_fee_tax_amount', $feeTaxAmount);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock, 'paymentFee' => $paymentFeeMock]);
        $instance->setOrder($orderMock);
        $instance->setSource($invoiceMock);
        $instance->setSourceField('buckaroo_fee');

        $this->assertSame($expected, $instance->getTotalsForDisplay());
    }
}
