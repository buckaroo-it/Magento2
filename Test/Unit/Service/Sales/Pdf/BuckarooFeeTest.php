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
namespace TIG\Buckaroo\Test\Unit\Service\Sales\Pdf;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\Config\Source\Display\Type;
use TIG\Buckaroo\Service\Sales\Pdf\BuckarooFee;
use TIG\Buckaroo\Test\BaseTest;

class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * @return array
     */
    public function getTotalsForDisplayProvider()
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
     * @dataProvider getTotalsForDisplayProvider
     */
    public function testGetTotalsForDisplay($amountExclTax, $amountInclTax, $label, $displayType, $expected)
    {
        $scopeInterfaceMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $scopeInterfaceMock->expects($this->once())->method('getValue')->willReturn($displayType);

        $paymentFeeMock = $this->getFakeMock(PaymentFee::class)->setMethods(['getBuckarooPaymentFeeLabel'])->getMock();
        $paymentFeeMock->expects($this->once())->method('getBuckarooPaymentFeeLabel')->willReturn($label);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getStore', 'formatPriceTxt'])->getMock();
        $orderMock->expects($this->once())->method('getStore')->willReturn(0);
        $orderMock->expects($this->exactly(2))
            ->method('formatPriceTxt')
            ->willReturnOnConsecutiveCalls($amountExclTax, $amountInclTax);

        $invoiceMock = $this->getFakeMock(Order\Invoice::class)->getMock();

        $instance = $this->getInstance(['scopeConfig' => $scopeInterfaceMock, 'paymentFee' => $paymentFeeMock]);
        $instance->setOrder($orderMock);
        $instance->setSource($invoiceMock);

        $result = $instance->getTotalsForDisplay();
        $this->assertEquals($expected, $result);
    }
}
