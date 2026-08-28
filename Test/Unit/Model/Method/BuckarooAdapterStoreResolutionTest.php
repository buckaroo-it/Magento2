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

namespace Buckaroo\Magento2\Test\Unit\Model\Method;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\TestCase;

/**
 * Finding #8.
 *
 * Magento calls setStore() on a *quote* payment but never on an *order* payment, so on the admin
 * order view, the order grid and the invoice/creditmemo create pages the adapter has no store and
 * every getConfigData() read falls through to the ambient store - the default store view, not the
 * store the order was placed in. The adapter has to derive it from the payment itself.
 */
class BuckarooAdapterStoreResolutionTest extends TestCase
{
    /**
     * @return \ReflectionMethod
     */
    private function resolver(): \ReflectionMethod
    {
        $method = new \ReflectionMethod(BuckarooAdapter::class, 'getResolvedStoreId');
        $method->setAccessible(true);

        return $method;
    }

    private function adapterWith($store, $infoInstance): BuckarooAdapter
    {
        $adapter = $this->getMockBuilder(BuckarooAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStore', 'getInfoInstance'])
            ->getMock();
        $adapter->method('getStore')->willReturn($store);
        if ($infoInstance instanceof \Throwable) {
            $adapter->method('getInfoInstance')->willThrowException($infoInstance);
        } else {
            $adapter->method('getInfoInstance')->willReturn($infoInstance);
        }

        return $adapter;
    }

    public function testPrefersAnExplicitlySetStore(): void
    {
        $adapter = $this->adapterWith(2, null);

        $this->assertSame(2, $this->resolver()->invoke($adapter));
    }

    public function testDerivesTheStoreFromTheOrderWhenNoneWasSet(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn('2');

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertSame(2, $this->resolver()->invoke($this->adapterWith(null, $payment)));
    }

    public function testDerivesTheStoreFromTheQuoteWhenNoneWasSet(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn('3');

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getQuote')->willReturn($quote);

        $this->assertSame(3, $this->resolver()->invoke($this->adapterWith(null, $payment)));
    }

    public function testReturnsNullWhenThePaymentHasNoOrder(): void
    {
        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getOrder')->willReturn(null);

        $this->assertNull($this->resolver()->invoke($this->adapterWith(null, $payment)));
    }

    /**
     * getInfoInstance() throws when no payment is attached yet, which happens on the payment
     * method list. That must degrade to the ambient store, not blow up the page.
     */
    public function testReturnsNullWhenThereIsNoPaymentAtAll(): void
    {
        $adapter = $this->adapterWith(null, new \Exception('no payment'));

        $this->assertNull($this->resolver()->invoke($adapter));
    }
}
