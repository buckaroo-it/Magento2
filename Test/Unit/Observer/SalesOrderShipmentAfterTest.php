<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Observer;

use PHPUnit\Framework\Attributes\DataProvider;

class SalesOrderShipmentAfterTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Observer\SalesOrderShipmentAfter';

    public function testRollbackCancelsEveryRegisteredInvoiceItem(): void
    {
        $instance = $this->getInstance();

        // Invoice\Item::cancel() is Magento's exact inverse of Invoice\Item::register(): it
        // subtracts only this invoice's quantities, so previously persisted invoices stay intact.
        $firstItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $firstItem->expects($this->once())->method('cancel');

        $secondItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $secondItem->expects($this->once())->method('cancel');

        $invoiceMock = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoiceMock->method('getAllItems')->willReturn([$firstItem, $secondItem]);

        $this->invokeArgs('rollBackRegisteredInvoiceValues', [$invoiceMock], $instance);
    }

    /**
     * A discounted order used to be invoiced in full on a partial shipment, which
     * captured the whole Klarna authorization and billed the shopper for unshipped lines.
     */
    public function testADiscountedOrderIsInvoicedForTheShippedItemsOnly(): void
    {
        $qtys = [12 => 1.0];

        $createInvoiceServiceMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceServiceMock->method('getQtysFromShipment')->willReturn($qtys);

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getDiscountAmount')->willReturn(-16.99);

        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        // Stop right after prepareInvoice: register() needs a fully built order.
        $invoiceMock->method('getTotalQty')->willReturn(0.0);

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->expects($this->once())
            ->method('prepareInvoice')
            ->with($orderMock, $qtys)
            ->willReturn($invoiceMock);

        $instance = $this->getInstance([
            'invoiceService' => $invoiceServiceMock,
            'createInvoiceService' => $createInvoiceServiceMock,
        ]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty(
            'shipment',
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock(),
            $instance
        );

        $this->assertNull($this->invoke('createInvoice', $instance));
    }

    /**
     * A shipment invoiced on its own can be covered entirely by store credit or a gift card, and
     * Magento then prices that invoice at 0.00 - it charges the payment method nothing. Asking the
     * gateway to take zero is refused ("amount is invalid for the action Pay"), and on order
     * 300000009 the whole invoice was lost with the failed capture. There is nothing to take, so
     * it must be captured offline.
     *
     */
    #[DataProvider('invoiceTotalProvider')]
    public function testAnInvoiceThatChargesNothingIsNotSentToTheGateway(float $grandTotal, bool $expected): void
    {
        $instance = $this->getInstance();

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getGrandTotal')->willReturn($grandTotal);

        $this->assertSame(
            $expected,
            $this->invokeArgs('hasNothingToCapture', [$invoice], $instance)
        );
    }

    /**
     * @return array
     */
    public static function invoiceTotalProvider(): array
    {
        return [
            'covered in full by store credit' => [0.0, true],
            'a rounding remnant is still nothing' => [0.004, true],
            'half a cent rounds up to a cent' => [0.005, false],
            'a cent is money and goes to the gateway' => [0.01, false],
            'a normal invoice goes to the gateway' => [27.82, false],
        ];
    }

    public function testNothingIsInvoicedOnceTheOrderHasNoInvoiceableItemsLeft(): void
    {
        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(false);
        $orderMock->method('getDiscountAmount')->willReturn(-16.99);

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->expects($this->never())->method('prepareInvoice');

        $instance = $this->getInstance(['invoiceService' => $invoiceServiceMock]);
        $this->setProperty('order', $orderMock, $instance);

        $this->assertNull($this->invoke('createInvoice', $instance));
    }

    /**
     * Klarna KP supports partial fulfilment, so the shipment of the remaining lines
     * must be able to invoice and capture them even though a first invoice already exists.
     */
    public function testASecondShipmentOfAKlarnaKpOrderStillInvoices(): void
    {
        $invoiceService = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceService->expects($this->once())->method('prepareInvoice')->willReturn(
            $this->makeEmptyPreparedInvoice()
        );

        $this->runExecuteFor('buckaroo_magento2_klarnakp', $invoiceService, true);
    }

    /**
     * Every authorize-based method supports partial fulfilment, so a later shipment must be able
     * to invoice and capture its own lines too.
     *
     * @param string $methodCode
     */
    #[DataProvider('authorizeMethodProvider')]
    public function testASecondShipmentAlsoInvoicesForTheOtherAuthorizeMethods(string $methodCode): void
    {
        $invoiceService = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceService->expects($this->once())->method('prepareInvoice')->willReturn(
            $this->makeEmptyPreparedInvoice()
        );

        $this->runExecuteFor($methodCode, $invoiceService, true);
    }

    public static function authorizeMethodProvider(): array
    {
        return [
            'Klarna MoR' => ['buckaroo_magento2_klarna'],
            'Afterpay' => ['buckaroo_magento2_afterpay20'],
            'a generic Buckaroo authorize method' => ['buckaroo_magento2_billink'],
        ];
    }

    /**
     * A method that captures at order time still invoices once, through the general setting
     * service - that path is untouched.
     */
    public function testANonAuthorizeMethodStillInvoicesOnlyOnce(): void
    {
        $invoiceService = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceService->expects($this->never())->method('prepareInvoice');

        $createInvoiceService = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceService->method('getQtysFromShipment')->willReturn([12 => 1.0]);
        $createInvoiceService->expects($this->never())->method('createInvoiceGeneralSetting');

        $this->runExecuteFor('buckaroo_magento2_ideal', $invoiceService, true, 'order', $createInvoiceService);
    }

    /**
     * The first shipment of a Klarna KP order invoices as it always did.
     */
    public function testTheFirstShipmentOfAKlarnaKpOrderInvoices(): void
    {
        $invoiceService = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceService->expects($this->once())->method('prepareInvoice')->willReturn(
            $this->makeEmptyPreparedInvoice()
        );

        $this->runExecuteFor('buckaroo_magento2_klarnakp', $invoiceService, false);
    }

    /**
     * Run the observer for a payment method whose "invoice after shipment" setting is on.
     *
     * @param string      $methodCode
     * @param object      $invoiceService
     * @param bool        $hasInvoices
     * @param string      $paymentAction
     * @param object|null $createInvoiceService
     *
     * @return void
     */
    private function runExecuteFor(
        string $methodCode,
        $invoiceService,
        bool $hasInvoices,
        string $paymentAction = 'authorize',
        $createInvoiceService = null
    ): void {
        $configProvider = $this->getFakeMock('Buckaroo\\Magento2\\Model\\ConfigProvider\\Method\\Klarnakp')
            ->disableOriginalConstructor()
            ->getMock();
        $configProvider->method('isInvoiceCreatedAfterShipment')->willReturn(true);
        $configProviderFactory = $this->getFakeMock('Buckaroo\\Magento2\\Model\\ConfigProvider\\Factory')
            ->getMock();
        $configProviderFactory->method('get')->willReturn($configProvider);

        $methodInstance = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Method\\BuckarooAdapter')->getMock();
        $methodInstance->method('getCode')->willReturn($methodCode);
        $methodInstance->method('getConfigPaymentAction')->willReturn($paymentAction);

        $payment = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Payment')->getMock();
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        // Keeps the generic branch away from the account configuration.
        $payment->method('getAdditionalInformation')->willReturn(
            \Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions::SHIPMENT
        );

        $order = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('canInvoice')->willReturn(true);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getAllItems')->willReturn([]);

        $orderRepository = $this->getFakeMock('Magento\\Sales\\Api\\OrderRepositoryInterface')->getMock();
        $orderRepository->method('get')->willReturn($order);

        if ($createInvoiceService === null) {
            $createInvoiceService = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
                ->getMock();
            $createInvoiceService->method('getQtysFromShipment')->willReturn([12 => 1.0]);
        }

        $instance = $this->getInstance([
            'invoiceService' => $invoiceService,
            'configProviderFactory' => $configProviderFactory,
            'orderRepository' => $orderRepository,
            'createInvoiceService' => $createInvoiceService,
        ]);

        $shipment = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock();
        $shipment->method('getOrderId')->willReturn(1);

        // Event::getShipment() is a DataObject magic getter; a real Event carrying the data
        // avoids mocking a non-existent method (MockBuilder::addMethods() removed in PHPUnit 12).
        $event = new \Magento\Framework\Event(['shipment' => $shipment]);
        $observer = $this->getFakeMock('Magento\\Framework\\Event\\Observer')->getMock();
        $observer->method('getEvent')->willReturn($event);

        $instance->execute($observer);
    }

    /**
     * An invoice that prepares to nothing, so createInvoice() returns before register().
     *
     * @return object
     */
    private function makeEmptyPreparedInvoice()
    {
        $invoice = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoice->method('getTotalQty')->willReturn(0.0);

        return $invoice;
    }

    /**
     * Invoice::register() writes the invoiced quantities onto the order items before
     * it attempts the capture, so a capture that throws must still be rolled back. The flag that
     * gates the rollback used to be set only after register() returned, which left the order
     * reporting invoiced items with no invoice entity once handleInvoiceFailure() saved it.
     */
    public function testACaptureThatThrowsRollsBackTheRegisteredQuantities(): void
    {
        $invoiceItem = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice\\Item')->getMock();
        $invoiceItem->expects($this->once())
            ->method('cancel')
            ->willReturnSelf();

        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoiceMock->method('getTotalQty')->willReturn(1.0);
        $invoiceMock->method('getAllItems')->willReturn([$invoiceItem]);
        // What the gateway does on a rejected capture.
        $invoiceMock->method('register')->willThrowException(
            new \Exception('Sum of given articles (3,36) is not equal to the given amount (19,36).')
        );

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->method('prepareInvoice')->willReturn($invoiceMock);

        $createInvoiceServiceMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceServiceMock->method('getQtysFromShipment')->willReturn([12 => 1.0]);

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getDiscountAmount')->willReturn(-16.99);
        $orderMock->method('getPayment')->willReturn(
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Payment')->getMock()
        );
        // The failure must still be recorded on the order.
        $orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $orderRepositoryMock = $this->getFakeMock('Magento\\Sales\\Api\\OrderRepositoryInterface')->getMock();
        $orderRepositoryMock->expects($this->once())->method('save');

        $instance = $this->getInstance([
            'invoiceService' => $invoiceServiceMock,
            'createInvoiceService' => $createInvoiceServiceMock,
            'orderRepository' => $orderRepositoryMock,
        ]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty(
            'shipment',
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock(),
            $instance
        );

        $this->assertNull($this->invoke('createInvoice', $instance));
    }

    /**
     * Paying an invoice can draw on a capture made earlier in Plaza. When the invoice is not saved
     * after all, the failure handler still saves the order, so the drawn amount has to be put back
     * first - otherwise it is gone while no invoice accounts for it.
     */
    public function testAFailedInvoiceGivesBackTheCapturedAmountItDrewOn(): void
    {
        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoiceMock->method('getTotalQty')->willReturn(1.0);
        $invoiceMock->method('getAllItems')->willReturn([]);
        $invoiceMock->method('register')->willThrowException(new \Exception('Could not save the invoice'));

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->method('prepareInvoice')->willReturn($invoiceMock);

        $createInvoiceServiceMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceServiceMock->method('getQtysFromShipment')->willReturn([12 => 1.0]);

        $paymentMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Payment')->getMock();

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $snapshot = ['amount' => 66.15, 'transaction_key' => 'PLAZA_KEY'];
        $uninvoicedCapturesMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\UninvoicedCaptures')
            ->getMock();
        $uninvoicedCapturesMock->method('snapshot')->with($orderMock)->willReturn($snapshot);
        $uninvoicedCapturesMock->expects($this->once())->method('restore')->with($orderMock, $snapshot);

        $orderRepositoryMock = $this->getFakeMock('Magento\\Sales\\Api\\OrderRepositoryInterface')->getMock();
        $orderRepositoryMock->expects($this->once())->method('save');

        $instance = $this->getInstance([
            'invoiceService' => $invoiceServiceMock,
            'createInvoiceService' => $createInvoiceServiceMock,
            'orderRepository' => $orderRepositoryMock,
            'uninvoicedCaptures' => $uninvoicedCapturesMock,
        ]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty(
            'shipment',
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock(),
            $instance
        );

        $this->assertNull($this->invoke('createInvoice', $instance));
    }

    /**
     * A failure before register() must NOT roll back - nothing was written yet, and subtracting
     * would corrupt the order in the other direction.
     */
    public function testAFailureBeforeRegisterDoesNotRollBack(): void
    {
        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoiceMock->method('getTotalQty')->willThrowException(new \Exception('boom'));
        $invoiceMock->expects($this->never())->method('getAllItems');

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->method('prepareInvoice')->willReturn($invoiceMock);

        $createInvoiceServiceMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceServiceMock->method('getQtysFromShipment')->willReturn([12 => 1.0]);

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getDiscountAmount')->willReturn(0.0);

        $instance = $this->getInstance([
            'invoiceService' => $invoiceServiceMock,
            'createInvoiceService' => $createInvoiceServiceMock,
        ]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty(
            'shipment',
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock(),
            $instance
        );

        $this->assertNull($this->invoke('createInvoice', $instance));
    }

    /**
     * `buckaroo_already_captured` is set by the push on the FIRST capture and never
     * cleared. On an order that captures per shipment it would send every later invoice offline,
     * marking it paid while the money never leaves the gateway.
     *
     * @param bool  $alreadyCapturedFlag
     * @param float $totalPaid
     * @param float $totalInvoiced
     * @param float $invoiceTotal
     * @param bool  $expectOffline
     */
    #[DataProvider('captureCaseProvider')]
    public function testTheCaptureCaseFollowsTheMoneyNotTheFlag(
        bool $alreadyCapturedFlag,
        float $totalPaid,
        float $totalInvoiced,
        float $invoiceTotal,
        bool $expectOffline
    ): void {
        $paymentMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn($alreadyCapturedFlag);

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getTotalPaid')->willReturn($totalPaid);
        $orderMock->method('getTotalInvoiced')->willReturn($totalInvoiced);

        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoiceMock->method('getGrandTotal')->willReturn($invoiceTotal);

        $instance = $this->getInstance();
        $this->setProperty('order', $orderMock, $instance);

        $this->assertSame(
            $expectOffline,
            $this->invokeArgs('isAlreadyPaidFor', [$invoiceMock], $instance)
        );
    }

    public static function captureCaseProvider(): array
    {
        return [
            // Reactivation: a push credited the capture before any invoice existed.
            'push paid before the invoice existed' => [true, 38.72, 0.0, 38.72, true],
            // A later shipment on a per-shipment order: not paid for yet.
            'a later shipment on a per-shipment order is not paid yet' =>
                [true, 31.46, 31.46, 19.36, false],
            'partly credited, but not enough for this invoice' => [true, 40.00, 31.46, 19.36, false],
            'no flag at all' => [false, 38.72, 0.0, 38.72, false],
            'credited amount short by a cent' => [true, 50.35, 31.0, 19.36, false],
            'credited amount short by a fraction of a cent' => [true, 50.351, 31.0, 19.36, false],
            'credited amount rounds up to the invoice total' => [true, 20.355, 1.0, 19.36, true],
            'invoice total rounds down to the credited amount' => [true, 50.35, 31.0, 19.354, true],
        ];
    }

    /**
     * The capture case set on the invoice decides whether register() asks the gateway for money,
     * so it has to be on the invoice before register() runs.
     *
     * @param float  $invoiceTotal
     * @param bool   $alreadyCapturedFlag
     * @param float  $totalPaid
     * @param float  $totalInvoiced
     * @param string $expectedCaptureCase
     */
    #[DataProvider('createInvoiceCaptureCaseProvider')]
    public function testCreateInvoiceRequestsTheCaptureCaseBeforeRegistering(
        float $invoiceTotal,
        bool $alreadyCapturedFlag,
        float $totalPaid,
        float $totalInvoiced,
        string $expectedCaptureCase
    ): void {
        $calls = [];

        $paymentMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturnCallback(
            static fn ($key) => $key === 'buckaroo_already_captured' ? $alreadyCapturedFlag : null
        );

        $orderMock = $this->getFakeMock('Magento\\Sales\\Model\\Order')->getMock();
        $orderMock->method('canInvoice')->willReturn(true);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getTotalPaid')->willReturn($totalPaid);
        $orderMock->method('getTotalInvoiced')->willReturn($totalInvoiced);
        $orderMock->method('getStatus')->willReturn('processing');

        $invoiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Order\\Invoice')->getMock();
        $invoiceMock->method('getTotalQty')->willReturn(1.0);
        $invoiceMock->method('getGrandTotal')->willReturn($invoiceTotal);
        $invoiceMock->method('getOrder')->willReturn($orderMock);
        // setRequestedCaptureCase() is a DataObject magic setter, so it arrives through __call().
        $invoiceMock->method('__call')
            ->willReturnCallback(function ($name, $args) use (&$calls, $invoiceMock) {
                $calls[] = $name . '(' . implode(',', $args) . ')';
                return $invoiceMock;
            });
        $invoiceMock->expects($this->once())
            ->method('register')
            ->willReturnCallback(function () use (&$calls, $invoiceMock) {
                $calls[] = 'register';
                return $invoiceMock;
            });

        $invoiceServiceMock = $this->getFakeMock('Magento\\Sales\\Model\\Service\\InvoiceService')->getMock();
        $invoiceServiceMock->method('prepareInvoice')->willReturn($invoiceMock);

        $createInvoiceServiceMock = $this->getFakeMock('Buckaroo\\Magento2\\Model\\Service\\CreateInvoice')
            ->getMock();
        $createInvoiceServiceMock->method('getQtysFromShipment')->willReturn([12 => 1.0]);

        $transactionMock = $this->getFakeMock('Magento\\Framework\\DB\\Transaction')->getMock();
        $transactionMock->method('addObject')->willReturnSelf();
        $transactionMock->expects($this->once())->method('save');
        $transactionFactoryMock = $this->getFakeMock('Magento\\Framework\\DB\\TransactionFactory')->getMock();
        $transactionFactoryMock->method('create')->willReturn($transactionMock);

        $instance = $this->getInstance([
            'invoiceService' => $invoiceServiceMock,
            'createInvoiceService' => $createInvoiceServiceMock,
            'transactionFactory' => $transactionFactoryMock,
        ]);
        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty(
            'shipment',
            $this->getFakeMock('Magento\\Sales\\Model\\Order\\Shipment')->getMock(),
            $instance
        );

        $this->assertSame($invoiceMock, $this->invoke('createInvoice', $instance));
        $this->assertSame(['setRequestedCaptureCase(' . $expectedCaptureCase . ')', 'register'], $calls);
    }

    public static function createInvoiceCaptureCaseProvider(): array
    {
        return [
            'covered in full by store credit' => [0.0, false, 0.0, 0.0, 'offline'],
            'zero total with the flag set' => [0.0, true, 0.0, 0.0, 'offline'],
            'push paid before the invoice existed' => [38.72, true, 38.72, 0.0, 'offline'],
            'a later shipment on a per-shipment order' => [19.36, true, 31.46, 31.46, 'online'],
            'partly credited, but not enough for this invoice' => [19.36, true, 40.00, 31.46, 'online'],
            'paid without the flag still captures' => [38.72, false, 38.72, 0.0, 'online'],
            'a normal invoice' => [27.82, false, 0.0, 0.0, 'online'],
        ];
    }

    /**
     * A "Ship Together" bundle records qty_shipped on the parent only, so the children have to be
     * mirrored or canShip() never returns false and the order cannot reach "complete". Mirroring
     * the parent's full ordered quantity would claim stock still in the warehouse.
     *
     * @param float $parentQtyOrdered
     * @param float $parentQtyShipped
     * @param float $childQtyOrdered
     * @param float $expected
     */
    #[DataProvider('bundleChildQtyProvider')]
    public function testABundleChildFollowsTheShareOfTheBundleThatShipped(
        float $parentQtyOrdered,
        float $parentQtyShipped,
        float $childQtyOrdered,
        float $expected
    ): void {
        $child = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $child->method('getQtyOrdered')->willReturn($childQtyOrdered);
        $child->method('getQtyShipped')->willReturn(0.0);
        $child->expects($this->once())->method('setQtyShipped')->with($expected);

        $this->runBundleQtySync($parentQtyOrdered, $parentQtyShipped, [$child]);
    }

    public static function bundleChildQtyProvider(): array
    {
        return [
            'a single bundle that shipped' => [1.0, 1.0, 1.0, 1.0],
            'half of the bundles shipped' => [2.0, 1.0, 2.0, 1.0],
            'all the bundles shipped' => [2.0, 2.0, 2.0, 2.0],
            'a child with more than one unit per bundle' => [2.0, 1.0, 6.0, 3.0],
            'one of three bundles shipped' => [3.0, 1.0, 3.0, 1.0],
        ];
    }

    /**
     * A child is never written down, so a later shipment of the same bundle cannot undo an
     * earlier one and a fully shipped child is left alone.
     */
    public function testAChildIsNeverWrittenBackDown(): void
    {
        $child = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $child->method('getQtyOrdered')->willReturn(2.0);
        $child->method('getQtyShipped')->willReturn(2.0);
        $child->expects($this->never())->method('setQtyShipped');

        $this->runBundleQtySync(2.0, 1.0, [$child], false);
    }

    /**
     * @param float $parentQtyOrdered
     * @param float $parentQtyShipped
     * @param array $children
     *
     * @return void
     */
    private function runBundleQtySync(
        float $parentQtyOrdered,
        float $parentQtyShipped,
        array $children,
        bool $expectSaved = true,
        string $productType = 'bundle',
        bool $shipSeparately = false
    ): void {
        $parent = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $parent->method('getProductType')->willReturn($productType);
        $parent->method('isShipSeparately')->willReturn($shipSeparately);
        $parent->method('getQtyOrdered')->willReturn($parentQtyOrdered);
        $parent->method('getQtyShipped')->willReturn($parentQtyShipped);
        $parent->method('getChildrenItems')->willReturn($children);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['getAllItems'])->getMock();
        $orderMock->method('getAllItems')->willReturn([$parent]);

        $orderRepository = $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock();
        $orderRepository->expects($expectSaved ? $this->once() : $this->never())->method('save')->with($orderMock);

        $instance = $this->getInstance(['orderRepository' => $orderRepository]);
        $this->setProperty('order', $orderMock, $instance);
        $this->invoke('syncBundleTogetherChildQtyShipped', $instance);

        $this->assertSame($expectSaved ? true : null, $orderMock->getIsInProcess());
    }

    public function testEachChangedChildIsSavedAndTheOrderAfterwards(): void
    {
        $unchanged = $this->makeBundleChild(2.0, 1.0);
        $unchanged->expects($this->never())->method('setQtyShipped');
        $changed = $this->makeBundleChild(4.0, 0.0);
        $changed->expects($this->once())->method('setQtyShipped')->with($this->identicalTo(2.0));

        $itemRepository = $this->getFakeMock('Magento\Sales\Api\OrderItemRepositoryInterface')->getMock();
        $itemRepository->expects($this->once())->method('save')->with($changed);

        $this->runBundleQtySyncWith($itemRepository, 2.0, 1.0, [$unchanged, $changed], true);
    }

    /**
     * A skipped item does not stop the bundles after it.
     */
    public function testBundlesAfterSkippedItemsAreStillSynced(): void
    {
        $simple = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $simple->method('getProductType')->willReturn('simple');
        $emptyBundle = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $emptyBundle->method('getProductType')->willReturn('bundle');
        $emptyBundle->method('getQtyOrdered')->willReturn(1.0);
        $emptyBundle->method('getQtyShipped')->willReturn(0.0);

        $child = $this->makeBundleChild(1.0, 0.0);
        $child->expects($this->once())->method('setQtyShipped')->with(1.0);
        $bundle = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $bundle->method('getProductType')->willReturn('bundle');
        $bundle->method('getQtyOrdered')->willReturn(1.0);
        $bundle->method('getQtyShipped')->willReturn(1.0);
        $bundle->method('getChildrenItems')->willReturn([$child]);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['getAllItems'])->getMock();
        $orderMock->method('getAllItems')->willReturn([$simple, $emptyBundle, $bundle]);

        $instance = $this->getInstance();
        $this->setProperty('order', $orderMock, $instance);
        $this->invoke('syncBundleTogetherChildQtyShipped', $instance);
    }

    public function testAChildThatCannotBeSavedDoesNotSaveTheOrder(): void
    {
        $child = $this->makeBundleChild(2.0, 0.0);
        $itemRepository = $this->getFakeMock('Magento\Sales\Api\OrderItemRepositoryInterface')->getMock();
        $itemRepository->expects($this->once())->method('save')->willThrowException(new \Exception('locked'));

        $this->runBundleQtySyncWith($itemRepository, 2.0, 2.0, [$child], false);
    }

    /**
     * Quantities keep Magento's four decimals.
     */
    public function testAChildShareKeepsFourDecimals(): void
    {
        $child = $this->makeBundleChild(1.0, 0.0);
        $child->expects($this->once())->method('setQtyShipped')->with($this->identicalTo(0.3333));

        $this->runBundleQtySync(3.0, 1.0, [$child]);
    }

    /**
     * Quantities read from the database are strings; the child still gets a float.
     */
    public function testAChildQuantityStoredAsStringIsWrittenAsFloat(): void
    {
        $child = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $child->method('getQtyOrdered')->willReturn('2.0000');
        $child->method('getQtyShipped')->willReturn('0.0000');
        $child->expects($this->once())->method('setQtyShipped')->with($this->identicalTo(2.0));

        $this->runBundleQtySync(1.0, 1.0, [$child]);
    }

    public static function skippedParentProvider(): array
    {
        return [
            'not a bundle'              => ['simple', false, 1.0, 1.0],
            'bundle shipped separately' => ['bundle', true, 1.0, 1.0],
            'bundle without ordered qty' => ['bundle', false, 0.0, 1.0],
        ];
    }

    #[DataProvider('skippedParentProvider')]
    public function testOtherItemsLeaveTheirChildrenAlone(
        string $productType,
        bool $shipSeparately,
        float $qtyOrdered,
        float $qtyShipped
    ): void {
        $child = $this->makeBundleChild(1.0, 0.0);
        $child->expects($this->never())->method('setQtyShipped');

        $this->runBundleQtySync($qtyOrdered, $qtyShipped, [$child], false, $productType, $shipSeparately);
    }

    /**
     * @param float $qtyOrdered
     * @param float $qtyShipped
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeBundleChild(float $qtyOrdered, float $qtyShipped)
    {
        $child = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $child->method('getQtyOrdered')->willReturn($qtyOrdered);
        $child->method('getQtyShipped')->willReturn($qtyShipped);

        return $child;
    }

    /**
     * @param object $itemRepository
     * @param float  $parentQtyOrdered
     * @param float  $parentQtyShipped
     * @param array  $children
     * @param bool   $expectSaved
     *
     * @return void
     */
    private function runBundleQtySyncWith(
        $itemRepository,
        float $parentQtyOrdered,
        float $parentQtyShipped,
        array $children,
        bool $expectSaved
    ): void {
        $parent = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $parent->method('getProductType')->willReturn('bundle');
        $parent->method('getQtyOrdered')->willReturn($parentQtyOrdered);
        $parent->method('getQtyShipped')->willReturn($parentQtyShipped);
        $parent->method('getChildrenItems')->willReturn($children);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['getAllItems'])->getMock();
        $orderMock->method('getAllItems')->willReturn([$parent]);

        $orderRepository = $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock();
        $orderRepository->expects($expectSaved ? $this->once() : $this->never())->method('save');

        $instance = $this->getInstance(['orderRepository' => $orderRepository, 'orderItemRepository' => $itemRepository]);
        $this->setProperty('order', $orderMock, $instance);
        $this->invoke('syncBundleTogetherChildQtyShipped', $instance);
    }

    /**
     * Which shipments invoice, and through which path.
     *
     * @return array
     */
    public static function routingProvider(): array
    {
        $none = ['klarnakp' => false, 'klarna' => false, 'afterpay20' => false];

        return [
            'klarna kp with its own setting'      =>
                ['buckaroo_magento2_klarnakp', ['klarnakp' => true] + $none, 'authorize', 'order', 1, 0],
            'klarna with its own setting'         =>
                ['buckaroo_magento2_klarna', ['klarna' => true] + $none, 'authorize', 'order', 1, 0],
            'afterpay authorize with its setting' =>
                ['buckaroo_magento2_afterpay20', ['afterpay20' => true] + $none, 'authorize', 'order', 1, 0],
            'afterpay capturing at order time'    =>
                ['buckaroo_magento2_afterpay20', ['afterpay20' => true] + $none, 'order', 'order', 0, 0],
            'another method is not a klarna or afterpay one' =>
                ['buckaroo_magento2_ideal', ['klarnakp' => true, 'klarna' => true, 'afterpay20' => true],
                    'authorize', 'order', 0, 0],
            'invoice handling on order'           =>
                ['buckaroo_magento2_ideal', $none, 'authorize', 'order', 0, 0],
            'not a buckaroo method'               => ['checkmo', $none, 'authorize', 'shipment', 0, 0],
            'capture method invoiced once'        =>
                ['buckaroo_magento2_ideal', $none, 'order', 'shipment', 0, 1],
            'missing flag falls back to the account setting' =>
                ['buckaroo_magento2_ideal', ['account' => 2] + $none, 'authorize', 'missing', 1, 0],
            'empty flag falls back to the account setting' =>
                ['buckaroo_magento2_ideal', ['account' => 2] + $none, 'authorize', 'empty', 1, 0],
            'account setting on payment' =>
                ['buckaroo_magento2_ideal', ['account' => 1] + $none, 'authorize', 'missing', 0, 0],
        ];
    }

    /**
     * @param string $methodCode
     * @param array  $methodSettings
     * @param string $paymentAction
     * @param string $invoiceHandling
     * @param int    $invoiceCalls
     * @param int    $generalSettingCalls
     */
    #[DataProvider('routingProvider')]
    public function testTheShipmentInvoicesThroughTheMatchingPath(
        string $methodCode,
        array $methodSettings,
        string $paymentAction,
        string $invoiceHandling,
        int $invoiceCalls,
        int $generalSettingCalls
    ): void {
        $handling = [
            'order' => \Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions::PAYMENT,
            'shipment' => \Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions::SHIPMENT,
            'missing' => null,
            'empty' => '',
        ][$invoiceHandling];

        $configProviderFactory = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Factory')->getMock();
        $configProviderFactory->method('get')->willReturnCallback(function ($key) use ($methodSettings) {
            if ($key === 'account') {
                $account = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Account')->getMock();
                $account->method('getInvoiceHandling')->willReturn($methodSettings['account']);
                return $account;
            }
            $config = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp')->getMock();
            $config->method('isInvoiceCreatedAfterShipment')->willReturn($methodSettings[$key] ?? false);
            return $config;
        });

        $methodInstance = $this->getFakeMock('Buckaroo\Magento2\Model\Method\BuckarooAdapter')->getMock();
        $methodInstance->method('getCode')->willReturn($methodCode);
        $methodInstance->method('getConfigPaymentAction')->willReturn($paymentAction);

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('getAdditionalInformation')->willReturn($handling);

        $child = $this->makeBundleChild(1.0, 0.0);
        $parent = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $parent->method('getProductType')->willReturn('bundle');
        $parent->method('getQtyOrdered')->willReturn(1.0);
        $parent->method('getQtyShipped')->willReturn(1.0);
        $parent->method('getChildrenItems')->willReturn([$child]);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('hasInvoices')->willReturn(false);
        $order->method('canInvoice')->willReturn(true);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getAllItems')->willReturn([$parent]);

        $invoiced = $invoiceCalls + $generalSettingCalls;
        $orderRepository = $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock();
        $orderRepository->method('get')->willReturn($order);
        $orderRepository->expects($this->exactly($invoiced))->method('save')->with($order);

        $itemRepository = $this->getFakeMock('Magento\Sales\Api\OrderItemRepositoryInterface')->getMock();
        $itemRepository->expects($this->exactly($invoiced))->method('save')->with($child);

        $invoiceService = $this->getFakeMock('Magento\Sales\Model\Service\InvoiceService')->getMock();
        $invoiceService->expects($this->exactly($invoiceCalls))->method('prepareInvoice')
            ->willReturn($this->makeEmptyPreparedInvoice());

        $createInvoiceService = $this->getFakeMock('Buckaroo\Magento2\Model\Service\CreateInvoice')->getMock();
        $createInvoiceService->method('getQtysFromShipment')->willReturn([12 => 1.0]);
        $createInvoiceService->expects($this->exactly($generalSettingCalls))
            ->method('createInvoiceGeneralSetting')
            ->with($order, [12 => 1.0]);

        $instance = $this->getInstance([
            'invoiceService' => $invoiceService,
            'configProviderFactory' => $configProviderFactory,
            'orderRepository' => $orderRepository,
            'orderItemRepository' => $itemRepository,
            'createInvoiceService' => $createInvoiceService,
        ]);

        $shipment = $this->getFakeMock('Magento\Sales\Model\Order\Shipment')->getMock();
        $instance->execute(new \Magento\Framework\Event\Observer([
            'event' => new \Magento\Framework\Event(['shipment' => $shipment]),
        ]));
    }

    /**
     * After register() the order is put in process without notifying the customer, and both
     * comments are added once the order is complete.
     */
    public function testARegisteredInvoiceUpdatesTheOrder(): void
    {
        $currency = $this->getFakeMock('Magento\Directory\Model\Currency')->getMock();
        $currency->method('formatTxt')->with(38.72)->willReturn('EUR 38.72');

        $order = $this->getFakeMock('Magento\Sales\Model\Order')
            ->onlyMethods(['canInvoice', 'getPayment', 'getStatus', 'getBaseCurrency', 'addCommentToStatusHistory'])
            ->getMock();
        $order->method('canInvoice')->willReturn(true);
        $order->method('getPayment')->willReturn($this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock());
        $order->method('getStatus')->willReturn('complete');
        $order->method('getBaseCurrency')->willReturn($currency);
        $order->setData('base_total_invoiced', 38.72);
        $comments = [];
        $order->method('addCommentToStatusHistory')->willReturnCallback(
            function ($comment) use (&$comments, $order) {
                $comments[] = (string)$comment;
                return $order;
            }
        );

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')
            ->onlyMethods(['getTotalQty', 'getGrandTotal', 'getOrder', 'register'])
            ->getMock();
        $invoice->method('getTotalQty')->willReturn(1.0);
        $invoice->method('getGrandTotal')->willReturn(38.72);
        $invoice->method('getOrder')->willReturn($order);

        $invoiceService = $this->getFakeMock('Magento\Sales\Model\Service\InvoiceService')->getMock();
        $invoiceService->method('prepareInvoice')->willReturn($invoice);

        $transaction = $this->getFakeMock('Magento\Framework\DB\Transaction')->getMock();
        $transaction->method('addObject')->willReturnSelf();
        $transactionFactory = $this->getFakeMock('Magento\Framework\DB\TransactionFactory')->getMock();
        $transactionFactory->method('create')->willReturn($transaction);

        $instance = $this->getInstance([
            'invoiceService' => $invoiceService,
            'transactionFactory' => $transactionFactory,
        ]);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('shipment', $this->getFakeMock('Magento\Sales\Model\Order\Shipment')->getMock(), $instance);

        $this->assertSame($invoice, $this->invoke('createInvoice', $instance));
        $this->assertSame(0, $order->getData('customer_note_notify'));
        $this->assertTrue($order->getData('is_in_process'));
        $this->assertSame(
            ['Automatically invoiced shipped items.', 'Total amount of EUR 38.72 has been paid'],
            $comments
        );
    }

    public function testRollbackIsANoOpForAnInvoiceWithoutItems(): void
    {
        $instance = $this->getInstance();

        $invoiceMock = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoiceMock->expects($this->once())->method('getAllItems')->willReturn([]);

        $this->invokeArgs('rollBackRegisteredInvoiceValues', [$invoiceMock], $instance);
    }
}
