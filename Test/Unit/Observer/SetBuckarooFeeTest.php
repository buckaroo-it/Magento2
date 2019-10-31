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
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Observer\SetBuckarooFee;

class SetBuckarooFeeTest extends BaseTest
{
    protected $instanceClass = SetBuckarooFee::class;

    /**
     * Test the happy path. No Buckaroo Payment Fee
     */
    public function testInvoiceRegisterHappyPath()
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getBaseBuckarooFee'])
            ->getMock();
        $quoteMock->expects($this->once())->method('getBaseBuckarooFee')->willReturn(false);

        $observerMock = $this->getMockBuilder(Observer::class)->setMethods(['getEvent', 'getQuote'])->getMock();
        $observerMock->expects($this->exactly(2))->method('getEvent')->willReturnSelf();
        $observerMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    /**
     * Test that the buckaroo fee and base buckaroo fee are set from the quote.
     */
    public function testInvoiceRegisterWithFee()
    {
        $buckarooFee = rand(1, 1000);
        $buckarooBaseFee = rand(1, 1000);
        $getBuckarooFeeInclTax = rand(1, 1000);
        $getBuckarooFeeTaxAmount = rand(1, 1000);
        $getBaseBuckarooFeeInclTax = rand(1, 1000);
        $getBuckarooFeeBaseTaxAmount = rand(1, 1000);

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setBuckarooFee',
                'setBaseBuckarooFee',
                'setBuckarooFeeInclTax',
                'setBuckarooFeeTaxAmount',
                'setBaseBuckarooFeeInclTax',
                'setBuckarooFeeBaseTaxAmount'
            ])
            ->getMock();
        $orderMock->expects($this->once())->method('setBuckarooFee')->with($buckarooFee);
        $orderMock->expects($this->once())->method('setBaseBuckarooFee')->with($buckarooBaseFee);
        $orderMock->expects($this->once())->method('setBuckarooFeeInclTax')->with($getBuckarooFeeInclTax);
        $orderMock->expects($this->once())->method('setBuckarooFeeTaxAmount')->with($getBuckarooFeeTaxAmount);
        $orderMock->expects($this->once())->method('setBaseBuckarooFeeInclTax')->with($getBaseBuckarooFeeInclTax);
        $orderMock->expects($this->once())->method('setBuckarooFeeBaseTaxAmount')->with($getBuckarooFeeBaseTaxAmount);

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBuckarooFee',
                'getBaseBuckarooFee',
                'getBuckarooFeeInclTax',
                'getBuckarooFeeTaxAmount',
                'getBaseBuckarooFeeInclTax',
                'getBuckarooFeeBaseTaxAmount'
            ])
            ->getMock();
        $quoteMock->method('getBuckarooFee')->willReturn($buckarooFee);
        $quoteMock->expects($this->exactly(2))->method('getBaseBuckarooFee')->willReturn($buckarooBaseFee);
        $quoteMock->method('getBuckarooFeeInclTax')->willReturn($getBuckarooFeeInclTax);
        $quoteMock->method('getBuckarooFeeTaxAmount')->willReturn($getBuckarooFeeTaxAmount);
        $quoteMock->method('getBaseBuckarooFeeInclTax')->willReturn($getBaseBuckarooFeeInclTax);
        $quoteMock->method('getBuckarooFeeBaseTaxAmount')->willReturn($getBuckarooFeeBaseTaxAmount);

        $observerMock = $this->getMockBuilder(Observer::class)
            ->setMethods(['getEvent', 'getOrder', 'getQuote'])
            ->getMock();
        $observerMock->expects($this->exactly(2))->method('getEvent')->willReturnSelf();
        $observerMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $observerMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }
}
