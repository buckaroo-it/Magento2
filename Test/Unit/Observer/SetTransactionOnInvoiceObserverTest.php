<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Buckaroo\Magento2\Observer\SetTransactionOnInvoiceObserver;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\State\CommandInterface;

/**
 * Magento's payment state commands document $amount as a BASE amount: they format it
 * with the order's base currency and compare it against base_grand_total to decide
 * capture finality. Passing the order-currency invoice total mislabels the capture
 * comment and can misclassify a full capture as partial.
 */
class SetTransactionOnInvoiceObserverTest extends BaseTest
{
    protected $instanceClass = SetTransactionOnInvoiceObserver::class;

    public function testStateCommandReceivesBaseInvoiceAmount(): void
    {
        $invoice = $this->getFakeMock(Invoice::class)->onlyMethods([])->getMock();
        $invoice->setData([
            'grand_total'      => 167.67, // order currency (PLN)
            'base_grand_total' => 39.10,  // base currency (EUR)
        ]);

        $payment = $this->getFakeMock(Payment::class)
            ->onlyMethods([
                'getMethod', 'getAdditionalInformation', 'getTransactionId', 'addTransaction',
                'prependMessage', 'addTransactionCommentsToOrder'
            ])
            ->disableOriginalConstructor()->getMock();
        $payment->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $payment->method('getAdditionalInformation')->willReturn(InvoiceHandlingOptions::SHIPMENT);
        $payment->method('getTransactionId')->willReturn(null);
        $payment->method('addTransaction')->willReturn(null);
        $payment->method('prependMessage')->willReturnArgument(0);

        $order = $this->getFakeMock(Order::class)->onlyMethods(['getPayment'])->getMock();
        $order->method('getPayment')->willReturn($payment);

        // Event/Observer are DataObjects: real instances resolve getInvoice()/getOrder()
        // through the magic getters, which mocks cannot declare
        $event = new Event(['invoice' => $invoice, 'order' => $order]);
        $observer = new Observer(['event' => $event]);

        $checkPaymentType = $this->createMock(CheckPaymentType::class);
        $checkPaymentType->method('isBuckarooMethod')->willReturn(true);

        $stateCommand = $this->createMock(CommandInterface::class);
        $stateCommand->expects($this->once())
            ->method('execute')
            ->with($payment, 39.10, $order)
            ->willReturn('Captured amount of EUR 39.10 online.');

        $instance = $this->getInstance([
            'stateCommand'         => $stateCommand,
            'checkPaymentType'     => $checkPaymentType,
            'createInvoiceService' => $this->createMock(CreateInvoice::class),
        ]);

        $instance->execute($observer);
    }
}
