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
     * A later shipment of the remaining lines must still be able to invoice and capture them.
     */
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

        $event = $this->getFakeMock('Magento\\Framework\\Event')->addMethods(['getShipment'])->getMock();
        $event->method('getShipment')->willReturn($shipment);
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

        $this->runBundleQtySync(2.0, 1.0, [$child]);
    }

    /**
     * @param float $parentQtyOrdered
     * @param float $parentQtyShipped
     * @param array $children
     *
     * @return void
     */
    private function runBundleQtySync(float $parentQtyOrdered, float $parentQtyShipped, array $children): void
    {
        $parent = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $parent->method('getProductType')->willReturn('bundle');
        $parent->method('isShipSeparately')->willReturn(false);
        $parent->method('getQtyOrdered')->willReturn($parentQtyOrdered);
        $parent->method('getQtyShipped')->willReturn($parentQtyShipped);
        $parent->method('getChildrenItems')->willReturn($children);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getAllItems')->willReturn([$parent]);

        $instance = $this->getInstance();
        $this->setProperty('order', $orderMock, $instance);
        $this->invoke('syncBundleTogetherChildQtyShipped', $instance);
    }

    public function testRollbackIsANoOpForAnInvoiceWithoutItems(): void
    {
        $instance = $this->getInstance();

        $invoiceMock = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoiceMock->expects($this->once())->method('getAllItems')->willReturn([]);

        $this->invokeArgs('rollBackRegisteredInvoiceValues', [$invoiceMock], $instance);
    }
}
