<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * A bank transfer paid in instalments must still be invoiced according to the Invoice Handling
 * setting. Magento only creates an invoice for a capture it considers final, and it decides that
 * by comparing the captured amount against base_total_due - so the instalment that settles the
 * order may not have the running totals deducted from it before registerCaptureNotification()
 * runs, or the settling payment reads as a partial capture and no invoice is ever created.
 */
class TransferInvoiceHandlingTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\TransferProcessor';

    /**
     * On payment: the settling instalment leaves the totals to registerCaptureNotification().
     */
    public function testSettlingInstalmentDefersTotalsWhenInvoicingOnPayment(): void
    {
        $orderRequestService = $this->getFakeMock(OrderRequestService::class)->getMock();
        $orderRequestService->expects($this->never())->method('saveAndReloadOrder');

        $instance = $this->getInstanceWith(InvoiceHandlingOptions::PAYMENT, $orderRequestService);

        $paymentDetails = ['amount' => 20.05, 'description' => ''];

        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
        $this->assertArrayNotHasKey('state', $paymentDetails);
    }

    /**
     * On shipment: no invoice accounts for the payment, so the running totals are still updated.
     */
    public function testSettlingInstalmentUpdatesTotalsWhenInvoicingOnShipment(): void
    {
        $orderRequestService = $this->getFakeMock(OrderRequestService::class)->getMock();
        $orderRequestService->expects($this->once())->method('saveAndReloadOrder');

        $instance = $this->getInstanceWith(InvoiceHandlingOptions::SHIPMENT, $orderRequestService);

        $paymentDetails = ['amount' => 20.05, 'description' => ''];

        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
    }

    /**
     * A genuinely partial instalment never invoices, whatever the setting says.
     */
    public function testPartialInstalmentIsNotInvoicedOnPayment(): void
    {
        $orderRequestService = $this->getFakeMock(OrderRequestService::class)->getMock();
        $orderRequestService->expects($this->once())->method('saveAndReloadOrder');

        $instance = $this->getInstanceWith(InvoiceHandlingOptions::PAYMENT, $orderRequestService);

        $paymentDetails = ['amount' => 10.00, 'description' => ''];

        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance));
        $this->assertSame(Order::STATE_NEW, $paymentDetails['state']);
    }

    /**
     * Build a processor whose order still owes 20.05 of a 40.05 total, with 20.00 already paid.
     *
     * @param int $invoiceHandling
     * @param \PHPUnit\Framework\MockObject\MockObject $orderRequestService
     *
     * @return \Buckaroo\Magento2\Model\Push\TransferProcessor
     */
    private function getInstanceWith(int $invoiceHandling, $orderRequestService)
    {
        $methodInstance = $this->getFakeMock(MethodInterface::class)->getMock();
        $methodInstance->method('getConfigData')->willReturn(null);

        $payment = $this->getFakeMock(Payment::class)->getMock();
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->getFakeMock(Order::class)->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getTotalDue')->willReturn(20.05);
        $order->method('getBaseTotalDue')->willReturn(20.05);
        $order->method('getTotalPaid')->willReturn(20.00);
        $order->method('getBaseTotalPaid')->willReturn(20.00);
        $order->method('getGrandTotal')->willReturn(40.05);
        $order->method('getBaseGrandTotal')->willReturn(40.05);

        $configAccount = $this->getFakeMock(Account::class)->getMock();
        $configAccount->method('getInvoiceHandling')->willReturn($invoiceHandling);

        $instance = $this->getInstance([
            'orderRequestService' => $orderRequestService,
            'configAccount'       => $configAccount,
        ]);

        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);

        return $instance;
    }
}
