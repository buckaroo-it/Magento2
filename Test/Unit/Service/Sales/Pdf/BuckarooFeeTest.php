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
     * @return array
     */
    public static function getTotalsForDisplayProvider()
    {
        return [
            'display incl. tax' => [
                1,
                2,
                'Buckaroo fee',
                Type::DISPLAY_TYPE_INCLUDING_TAX,
                [[
                    'amount' => 2,
                    'label' => 'Buckaroo fee:',
                    'font_size' => 7
                ]]
            ],
            'display excl. tax' => [
                3,
                4,
                'Transaction fee',
                Type::DISPLAY_TYPE_EXCLUDING_TAX,
                [[
                    'amount' => 3,
                    'label' => 'Transaction fee:',
                    'font_size' => 7
                ]]
            ],
            'display incl. and excl. tax' => [
                5,
                6,
                'Buckaroo transaction fee',
                Type::DISPLAY_TYPE_BOTH,
                [
                    [
                        'amount' => 5,
                        'label' => 'Buckaroo transaction fee (Excl. Tax):',
                        'font_size' => 7
                    ],
                    [
                        'amount' => 6,
                        'label' => 'Buckaroo transaction fee (Incl. Tax):',
                        'font_size' => 7
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $amountExclTax
     * @param $amountInclTax
     * @param $label
     * @param $displayType
     * @param $expected
     *
     */
    #[DataProvider('getTotalsForDisplayProvider')]
    public function testGetTotalsForDisplay($amountExclTax, $amountInclTax, $label, $displayType, $expected)
    {
        $scopeInterfaceMock = $this->createMock(ScopeConfigInterface::class);
        $scopeInterfaceMock->method('getValue')->willReturn($displayType);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->onlyMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->method('getBuckarooPaymentFeeLabel')->willReturn($label);

        $orderMock = $this->getFakeMock(Order::class)->onlyMethods(['getStore', 'formatPriceTxt'])->getMock();
        $orderMock->method('getStore')->willReturn(0);
        $orderMock->method('formatPriceTxt')
            ->willReturnOnConsecutiveCalls($amountExclTax, $amountInclTax);

        $invoiceMock = $this->getFakeMock(Order\Invoice::class)->getMock();

        $instance = $this->getInstance(['scopeConfig' => $scopeInterfaceMock, 'paymentFee' => $paymentFeeMock]);
        $instance->setOrder($orderMock);
        $instance->setSource($invoiceMock);

        $result = $instance->getTotalsForDisplay();
        $this->assertEquals($expected, $result);
    }
}
