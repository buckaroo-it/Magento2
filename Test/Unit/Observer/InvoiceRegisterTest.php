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
namespace TIG\Buckaroo\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Observer\InvoiceRegister;

class InvoiceRegisterTest extends BaseTest
{
    protected $instanceClass = InvoiceRegister::class;

    /**
     * Test the happy path. Nothing is changed.
     */
    public function testInvoiceRegisterHappyPath()
    {
        $observerMock = $this->getFakeMock(Observer::class)
            ->setMethods(['getEvent', 'getInvoice', 'getBaseBuckarooFee'])
            ->getMock();
        $observerMock->expects($this->once())->method('getEvent')->willReturnSelf();
        $observerMock->expects($this->once())->method('getInvoice')->willReturnSelf();
        $observerMock->expects($this->once())->method('getBaseBuckarooFee')->willReturn(false);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }

    /**
     * Test that the payment fee isset.
     */
    public function testInvoiceRegisterWithPaymentFee()
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods([
            'setBuckarooFeeInvoiced', 'setBaseBuckarooFeeInvoiced', 'setBuckarooFeeTaxAmountInvoiced',
            'setBuckarooFeeBaseTaxAmountInvoiced', 'setBuckarooFeeInclTaxInvoiced', 'setBaseBuckarooFeeInclTaxInvoiced',
            'getBuckarooFeeInvoiced', 'getBaseBuckarooFeeInvoiced', 'getBuckarooFeeTaxAmountInvoiced',
            'getBuckarooFeeBaseTaxAmountInvoiced', 'getBuckarooFeeInclTaxInvoiced', 'getBaseBuckarooFeeInclTaxInvoiced'
        ])->getMock();
        $orderMock->expects($this->once())->method('setBuckarooFeeInvoiced');
        $orderMock->expects($this->once())->method('setBaseBuckarooFeeInvoiced');
        $orderMock->expects($this->once())->method('setBuckarooFeeTaxAmountInvoiced');
        $orderMock->expects($this->once())->method('setBuckarooFeeBaseTaxAmountInvoiced');
        $orderMock->expects($this->once())->method('setBuckarooFeeInclTaxInvoiced');
        $orderMock->expects($this->once())->method('setBaseBuckarooFeeInclTaxInvoiced');
        $orderMock->expects($this->once())->method('getBuckarooFeeInvoiced');
        $orderMock->expects($this->once())->method('getBaseBuckarooFeeInvoiced');
        $orderMock->expects($this->once())->method('getBuckarooFeeTaxAmountInvoiced');
        $orderMock->expects($this->once())->method('getBuckarooFeeBaseTaxAmountInvoiced');
        $orderMock->expects($this->once())->method('getBuckarooFeeInclTaxInvoiced');
        $orderMock->expects($this->once())->method('getBaseBuckarooFeeInclTaxInvoiced');

        $observerMock = $this->getFakeMock(Observer::class)
            ->setMethods([
                'getEvent', 'getInvoice', 'getBaseBuckarooFee', 'getBuckarooFee', 'getBuckarooFeeTaxAmount',
                'getBuckarooFeeBaseTaxAmount', 'getBuckarooFeeInclTax', 'getBaseBuckarooFeeInclTax', 'getOrder'
            ])
            ->getMock();
        $observerMock->expects($this->once())->method('getEvent')->willReturnSelf();
        $observerMock->expects($this->once())->method('getInvoice')->willReturnSelf();
        $observerMock->expects($this->exactly(2))->method('getBaseBuckarooFee')->willReturn(true);
        $observerMock->expects($this->once())->method('getBuckarooFee');
        $observerMock->expects($this->once())->method('getBuckarooFeeTaxAmount');
        $observerMock->expects($this->once())->method('getBuckarooFeeBaseTaxAmount');
        $observerMock->expects($this->once())->method('getBuckarooFeeInclTax');
        $observerMock->expects($this->once())->method('getBaseBuckarooFeeInclTax');
        $observerMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }
}
