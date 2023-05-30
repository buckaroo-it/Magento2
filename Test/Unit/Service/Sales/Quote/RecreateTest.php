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

namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Quote;

use Magento\Checkout\Model\Cart;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Buckaroo\Magento2\Test\BaseTest;

class RecreateTest extends BaseTest
{
    protected $instanceClass = Recreate::class;

    public function testRecreate()
    {
        $quoteId = 135;
        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getQuoteId'])->getMock();
        $orderMock->expects($this->once())->method('getQuoteId')->willReturn($quoteId);

        $quoteMock = $this->getFakeMock(Quote::class)->setMethods(['load'])->getMock();
        $quoteMock->expects($this->once())->method('load')->willReturnSelf();

        $quoteFactoryMock = $this->getFakeMock(QuoteFactory::class)
            ->setMethods(['create'])
            ->getMock();
        $quoteFactoryMock->expects($this->once())->method('create')->willReturn($quoteMock);

        $cartMock = $this->getFakeMock(Cart::class)->setMethods(['setQuote', 'save'])->getMock();
        $cartMock->expects($this->once())->method('setQuote')->with($quoteMock)->willReturnSelf();
        $cartMock->expects($this->once())->method('save');

        $instance = $this->getInstance(['quoteFactory' => $quoteFactoryMock, 'cart' => $cartMock]);
        $instance->recreate($orderMock);

        $this->assertTrue($quoteMock->getIsActive());
        $this->assertEquals('1', $quoteMock->getTriggerRecollect());
        $this->assertNull($quoteMock->getReservedOrderId());
        $this->assertNull($quoteMock->getBuckarooFee());
        $this->assertNull($quoteMock->getBaseBuckarooFee());
        $this->assertNull($quoteMock->getBuckarooFeeTaxAmount());
        $this->assertNull($quoteMock->getBuckarooFeeBaseTaxAmount());
        $this->assertNull($quoteMock->getBuckarooFeeInclTax());
        $this->assertNull($quoteMock->getBaseBuckarooFeeInclTax());
    }
}
