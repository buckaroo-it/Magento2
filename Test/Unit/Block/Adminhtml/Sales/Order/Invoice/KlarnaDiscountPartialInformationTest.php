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
namespace TIG\Buckaroo\Test\Unit\Block\Adminhtml\Sales\Order\Invoice;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Block\Adminhtml\Sales\Order\Invoice\KlarnaDiscountPartialInformation;
use TIG\Buckaroo\Test\BaseTest;

class KlarnaDiscountPartialInformationTest extends BaseTest
{
    protected $instanceClass = KlarnaDiscountPartialInformation::class;

    /**
     * @return array
     */
    public function shouldShowWarningProvider()
    {
        return [
            'return false by partial capture' => [
                true,
                'tig_buckaroo_klarna',
                false
            ],
            'return false by incorrect method' => [
                false,
                'tig_buckaroo_ideal',
                false
            ],
            'return true' => [
                false,
                'tig_buckaroo_klarna',
                true
            ],
        ];
    }

    /**
     * @param $partialCapture
     * @param $method
     * @param $expected
     *
     * @dataProvider shouldShowWarningProvider
     */
    public function testShouldShowWarning($partialCapture, $method, $expected)
    {
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['canCapturePartial', 'getMethod'])->getMock();
        $paymentMock->expects($this->once())->method('canCapturePartial')->willReturn($partialCapture);
        $paymentMock->method('getMethod')->willReturn($method);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getPayment'])->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(['getOrder'])->getMock();
        $invoiceMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $registryMock = $this->getFakeMock(Registry::class)->setMethods(['registry'])->getMock();
        $registryMock->expects($this->once())->method('registry')->with('current_invoice')->willReturn($invoiceMock);

        $instance = $this->getInstance(['registry' => $registryMock]);
        $result = $this->invoke('shouldShowWarning', $instance);

        $this->assertEquals($expected, $result);
    }
}
