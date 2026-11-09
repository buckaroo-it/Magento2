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

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Service\UninvoicedCaptures;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;

/**
 * A capture that arrives before the order ships (a capture made in Buckaroo Plaza) creates no
 * invoice. It has to be recorded so the invoice created on shipment does not capture it again.
 */
class DeferredCaptureRecordTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\DefaultProcessor';

    public function testACaptureWithoutInvoiceIsRecordedForTheInvoicesThatFollow(): void
    {
        [$instance, $order, $payment] = $this->makeProcessor('PLAZA_CAPTURE_KEY');

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 66.15], $instance);

        $captures = new UninvoicedCaptures();
        $this->assertSame(66.15, $captures->getAmount($order));
        $this->assertSame('PLAZA_CAPTURE_KEY', $captures->getTransactionKey($order));
    }

    public function testTwoPartialCapturesAddUp(): void
    {
        [$instance, $order, $payment] = $this->makeProcessor('SECOND_KEY');

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 34.00], $instance);
        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 32.15], $instance);

        $this->assertSame(66.15, (new UninvoicedCaptures())->getAmount($order));
        $this->assertSame(66.15, $payment->getBaseAmountPaidOnline(), 'Added up, not overwritten');
    }

    /**
     * @param string $transactionKey
     *
     * @return array{0: object, 1: object, 2: object}
     */
    private function makeProcessor(string $transactionKey): array
    {
        $store = new \Magento\Framework\DataObject();
        $amounts = new \Magento\Framework\DataObject();

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn($key = null) => $key === null ? $store->getData() : $store->getData($key)
        );
        $payment->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use ($store, $payment) {
                $store->setData($key, $value);
                return $payment;
            }
        );
        foreach (['BaseAmountPaidOnline', 'BaseAmountPaid', 'AmountPaid'] as $field) {
            $payment->method('get' . $field)->willReturnCallback(static fn() => $amounts->getData($field));
            $payment->method('set' . $field)->willReturnCallback(
                static function ($value) use ($amounts, $field, $payment) {
                    $amounts->setData($field, $value);
                    return $payment;
                }
            );
        }

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseToOrderRate')->willReturn(1.0);

        $pushRequest = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $pushRequest->method('getTransactions')->willReturn($transactionKey);

        $instance = $this->getInstance(['uninvoicedCaptures' => new UninvoicedCaptures()]);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        return [$instance, $order, $payment];
    }
}
