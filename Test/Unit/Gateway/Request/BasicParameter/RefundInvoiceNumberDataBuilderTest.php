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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * BTI-1413 — "Invoice numbers for refunds have to be unique. We recommend to use the same
 * invoice number of the Pay or Capture transaction with an extra addition at the end like -1 or
 * -R." (Buckaroo RIVERTY request documentation; the general refund documentation is silent, and
 * Klarna accepts a repeated number.)
 *
 * Sending the bare order number for every refund made Riverty refuse the second one with
 * "Cannot create refund as invoice number already exists (creditNoteNumber)" on order 000000018.
 * The first refund keeps the number every method has always sent, so nothing changes for the
 * single-refund orders that make up almost all traffic.
 */
class RefundInvoiceNumberDataBuilderTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass =
        'Buckaroo\Magento2\Gateway\Request\BasicParameter\RefundInvoiceNumberDataBuilder';

    /**
     * @param int    $storedCreditmemos
     * @param string $expectedInvoice
     */
    #[DataProvider('refundNumberProvider')]
    public function testEveryRefundGetsItsOwnInvoiceNumber(
        int $storedCreditmemos,
        string $expectedInvoice
    ): void {
        $creditmemos = [];
        for ($i = 1; $i <= $storedCreditmemos; $i++) {
            $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')->getMock();
            $creditmemo->method('getId')->willReturn($i);
            $creditmemos[] = $creditmemo;
        }
        // A credit memo that has not been saved yet must not be counted.
        $inFlight = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')->getMock();
        $inFlight->method('getId')->willReturn(null);
        $creditmemos[] = $inFlight;

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getIncrementId')->willReturn('000000018');
        $order->method('getCreditmemosCollection')->willReturn($creditmemos);

        $result = $this->getInstance()->build($this->makeBuildSubject($order));

        $this->assertSame($expectedInvoice, $result['invoice']);
        $this->assertSame('000000018', $result['order'], 'The order number itself is unchanged');
    }

    public static function refundNumberProvider(): array
    {
        return [
            'first refund keeps the number it has always sent' => [0, '000000018'],
            'second refund' => [1, '000000018-R2'],
            'third refund' => [2, '000000018-R3'],
        ];
    }

    /**
     * Two refunds on the same order must never collide - that is the whole point.
     */
    public function testTwoRefundsOnOneOrderNeverShareANumber(): void
    {
        $numbers = [];

        foreach ([0, 1] as $stored) {
            $creditmemos = [];
            for ($i = 1; $i <= $stored; $i++) {
                $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')->getMock();
                $creditmemo->method('getId')->willReturn($i);
                $creditmemos[] = $creditmemo;
            }

            $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
            $order->method('getIncrementId')->willReturn('000000018');
            $order->method('getCreditmemosCollection')->willReturn($creditmemos);

            $numbers[] = $this->getInstance()->build($this->makeBuildSubject($order))['invoice'];
        }

        $this->assertSame($numbers, array_unique($numbers));
    }

    /**
     * @param object $order
     *
     * @return array
     */
    private function makeBuildSubject($order): array
    {
        $orderAdapter = $this->getFakeMock('Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter')->getMock();
        $orderAdapter->method('getOrder')->willReturn($order);

        $paymentDO = $this->getFakeMock('Magento\Payment\Gateway\Data\PaymentDataObjectInterface')->getMock();
        $paymentDO->method('getOrder')->willReturn($orderAdapter);
        $paymentDO->method('getPayment')->willReturn(
            $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock()
        );

        return ['payment' => $paymentDO];
    }
}
