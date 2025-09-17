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

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Observer\InvoiceRegister;

class InvoiceRegisterTest extends BaseTest
{
    protected $instanceClass = InvoiceRegister::class;

    /**
     * Test the happy path. Nothing is changed.
     */
    public function testInvoiceRegisterHappyPath()
    {
        $observerMock = $this->getFakeMock(Observer::class)
            ->onlyMethods(['getEvent'])
            ->addMethods(['getInvoice', 'getBaseBuckarooFee'])
            ->getMock();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getInvoice')->willReturnSelf();
        $observerMock->method('getBaseBuckarooFee')->willReturn(false);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }

    /**
     * Test that the payment fee isset.
     */
    public function testInvoiceRegisterWithPaymentFee()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->addMethods([
                'setBuckarooFeeInvoiced', 'setBaseBuckarooFeeInvoiced', 'setBuckarooFeeTaxAmountInvoiced',
                'setBuckarooFeeBaseTaxAmountInvoiced', 'setBuckarooFeeInclTaxInvoiced', 'setBaseBuckarooFeeInclTaxInvoiced',
                'getBuckarooFeeInvoiced', 'getBaseBuckarooFeeInvoiced', 'getBuckarooFeeTaxAmountInvoiced',
                'getBuckarooFeeBaseTaxAmountInvoiced', 'getBuckarooFeeInclTaxInvoiced', 'getBaseBuckarooFeeInclTaxInvoiced'
            ])
            ->getMock();
        $orderMock->method('setBuckarooFeeInvoiced');
        $orderMock->method('setBaseBuckarooFeeInvoiced');
        $orderMock->method('setBuckarooFeeTaxAmountInvoiced');
        $orderMock->method('setBuckarooFeeBaseTaxAmountInvoiced');
        $orderMock->method('setBuckarooFeeInclTaxInvoiced');
        $orderMock->method('setBaseBuckarooFeeInclTaxInvoiced');
        $orderMock->method('getBuckarooFeeInvoiced');
        $orderMock->method('getBaseBuckarooFeeInvoiced');
        $orderMock->method('getBuckarooFeeTaxAmountInvoiced');
        $orderMock->method('getBuckarooFeeBaseTaxAmountInvoiced');
        $orderMock->method('getBuckarooFeeInclTaxInvoiced');
        $orderMock->method('getBaseBuckarooFeeInclTaxInvoiced');

        $observerMock = $this->getFakeMock(Observer::class)
            ->onlyMethods(['getEvent'])
            ->addMethods(['getInvoice', 'getBaseBuckarooFee', 'getBuckarooFee', 'getBuckarooFeeTaxAmount',
                         'getBuckarooFeeBaseTaxAmount', 'getBuckarooFeeInclTax', 'getBaseBuckarooFeeInclTax', 'getOrder'])
            ->getMockForAbstractClass();
        $observerMock->method('getEvent')->willReturnSelf();
        $observerMock->method('getInvoice')->willReturnSelf();
        $observerMock->method('getBaseBuckarooFee')->willReturn(true);
        $observerMock->method('getBuckarooFee');
        $observerMock->method('getBuckarooFeeTaxAmount');
        $observerMock->method('getBuckarooFeeBaseTaxAmount');
        $observerMock->method('getBuckarooFeeInclTax');
        $observerMock->method('getBaseBuckarooFeeInclTax');
        $observerMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }
}
