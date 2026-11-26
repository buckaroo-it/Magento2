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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Controller\Redirect\Process;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Model\Service\Order\ReservationNumberStore;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Buckaroo\Magento2\Service\SpamLimitService;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Covers Process::execute(): the shopper's return from Buckaroo, per status code.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProcessTest extends BaseTest
{
    private const GENERAL_ERROR = 'Unfortunately an error occurred while processing your payment. '
        . 'Please try again. If this error persists, please choose a different payment method.';
    private const CANCELLED_ERROR = 'Payment cancelled. You can try again using the same or a different payment method.';
    private const ORDER_ID = 10;
    private const QUOTE_ID = 20;
    private const INCREMENT_ID = '000000123';
    private const RECREATED_QUOTE_ID = 77;

    protected $instanceClass = Process::class;

    /** @var array */
    private $scenario = [];

    /** @var array<int, array> */
    private $redirects = [];

    /** @var array<int, string> */
    private $rawRedirects = [];

    /** @var array<int, array> */
    private $sessionWrites = [];

    /** @var array<int, string> */
    private $errorMessages = [];

    /** @var array<int, string> */
    private $successMessages = [];

    /** @var array<int, string> */
    private $events = [];

    /** @var Order&MockObject */
    private $order;

    /** @var Payment&MockObject */
    private $payment;

    /** @var MethodInterface&MockObject */
    private $methodInstance;

    /** @var Quote&MockObject */
    private $quote;

    /** @var Quote&MockObject */
    private $recreatedQuote;

    /** @var PushRequestInterfaceStub&MockObject */
    private $pushRequest;

    /** @var OrderRequestService&MockObject */
    private $orderRequestService;

    /** @var LockManagerWrapper&MockObject */
    private $lockManager;

    /** @var OrderService&MockObject */
    private $orderService;

    /** @var Recreate&MockObject */
    private $quoteRecreate;

    /** @var OrderRepositoryInterface&MockObject */
    private $orderRepository;

    /** @var CartRepositoryInterface&MockObject */
    private $cartRepository;

    /** @var SpamLimitService&MockObject */
    private $spamLimitService;

    /** @var OrderStatusFactory&MockObject */
    private $orderStatusFactory;

    /** @var ReservationNumberStore&MockObject */
    private $reservationNumberStore;

    public function testSuccessRecordsTheOrderInTheSessionAndRedirectsToTheSuccessPage(): void
    {
        $instance = $this->buildInstance(['status' => '190', 'orderConfirmationEmail' => '1']);

        $this->lockManager->expects($this->once())->method('lockOrder')
            ->with(self::INCREMENT_ID, 5)->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlockOrder')->with(self::INCREMENT_ID);
        $this->quote->expects($this->once())->method('load')->with(self::QUOTE_ID);
        $this->quote->expects($this->once())->method('setReservedOrderId')->with(null);
        $this->payment->expects($this->once())->method('setAdditionalInformation')
            ->with('buckaroo_payment_in_transit', false);
        $this->orderRequestService->expects($this->once())->method('sendOrderEmail')->with($this->order, true);
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');
        $this->quoteRecreate->expects($this->never())->method('recreateById');
        $this->spamLimitService->expects($this->never())->method('updateRateLimiterCount');

        $instance->execute();

        $this->assertSame([['checkout/onepage/success', []]], $this->redirects);
        $this->assertSame([
            ['setRestoreQuoteLastOrder', [false]],
            ['setLastSuccessQuoteId', [self::QUOTE_ID]],
            ['setLastQuoteId', [self::QUOTE_ID]],
            ['setLastOrderId', [self::ORDER_ID]],
            ['setLastRealOrderId', [self::INCREMENT_ID]],
        ], $this->sessionWrites);
        $this->assertSame(['Your order has been placed successfully.'], $this->successMessages);
        $this->assertSame([], $this->errorMessages);
        $this->assertSame(['buckaroo_process_redirect_success_before'], $this->events);
    }

    /**
     * @param int  $invoiceHandling
     * @param bool $statusChanged
     */
    #[DataProvider('pendingProvider')]
    public function testPendingSetsThePendingStatusAndRedirectsToTheConfiguredSuccessUrl(
        int $invoiceHandling,
        bool $statusChanged
    ): void {
        $instance = $this->buildInstance([
            'status'          => '791',
            'successRedirect' => 'buckaroo/custom/success',
            'invoiceHandling' => $invoiceHandling,
        ]);

        $this->orderStatusFactory->method('get')->with(791, $this->order)->willReturn('buckaroo_pending');
        $this->order->expects($statusChanged ? $this->once() : $this->never())
            ->method('setStatus')->with('buckaroo_pending');
        $this->orderRepository->expects($statusChanged ? $this->once() : $this->never())
            ->method('save')->with($this->order);
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');

        $instance->execute();

        $this->assertSame([['buckaroo/custom/success', []]], $this->redirects);
        $this->assertSame(['Your order has been placed successfully.'], $this->successMessages);
        $this->assertContains(['setLastRealOrderId', [self::INCREMENT_ID]], $this->sessionWrites);
    }

    public static function pendingProvider(): array
    {
        return [
            'invoice on payment'  => [InvoiceHandlingOptions::PAYMENT, true],
            'invoice on shipment' => [InvoiceHandlingOptions::SHIPMENT, false],
        ];
    }

    /**
     * @param string $status
     * @param string $failureRedirect
     * @param string $expectedPath
     */
    #[DataProvider('failedProvider')]
    public function testFailureCancelsTheOrderRestoresTheCartAndRedirectsToTheFailurePage(
        string $status,
        string $failureRedirect,
        string $expectedPath
    ): void {
        $instance = $this->buildInstance(['status' => $status, 'failureRedirect' => $failureRedirect]);

        $this->quoteRecreate->expects($this->once())->method('recreate')->with($this->quote)
            ->willReturn($this->recreatedQuote);
        $this->recreatedQuote->expects($this->once())->method('setIsActive')->with(true);
        $this->cartRepository->expects($this->once())->method('save')->with($this->recreatedQuote);
        $this->orderService->expects($this->once())->method('cancel')
            ->with($this->order, (int)$status)->willReturn(true);
        $this->spamLimitService->expects($this->once())->method('updateRateLimiterCount')
            ->with($this->methodInstance);
        $this->orderRequestService->expects($this->never())->method('sendOrderEmail');
        $this->quote->expects($this->never())->method('setReservedOrderId');

        $instance->execute();

        $this->assertSame([[$expectedPath, []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
        $this->assertSame([], $this->successMessages);
        $this->assertSame([
            ['setRestoreQuoteLastOrder', [false]],
            ['replaceQuote', [$this->recreatedQuote]],
            ['setQuoteId', [self::RECREATED_QUOTE_ID]],
            ['setRestoreQuoteLastOrder', [false]],
        ], $this->sessionWrites);
        $this->assertSame(['buckaroo_process_handle_failed_before'], $this->events);
    }

    public static function failedProvider(): array
    {
        return [
            'failed, default failure page'  => ['490', '', 'checkout'],
            'rejected, configured page'     => ['690', 'checkout/cart', 'checkout/cart'],
            'validation failure'            => ['491', '', 'checkout'],
            'technical error'               => ['492', '', 'checkout'],
            'cancelled by merchant'         => ['891', '', 'checkout'],
        ];
    }

    public function testFailureUrlWithAFragmentIsSentAsAnAbsoluteRedirect(): void
    {
        $instance = $this->buildInstance(['status' => '490', 'failureRedirect' => 'checkout/#payment']);
        $this->orderService->expects($this->once())->method('cancel')->with($this->order, 490)->willReturn(true);

        $instance->execute();

        $this->assertSame(['https://shop.test/checkout#payment'], $this->rawRedirects);
        $this->assertSame([], $this->redirects);
    }

    public function testFailureWithRedirectToCheckoutRestoresTheQuoteAndOpensThePaymentStep(): void
    {
        $instance = $this->buildInstance(['status' => '490', 'failureRedirectToCheckout' => true]);
        $this->orderService->expects($this->once())->method('cancel')->with($this->order, 490)->willReturn(true);

        $instance->execute();

        $this->assertSame(['https://shop.test/checkout/?bk_e=1#payment'], $this->rawRedirects);
        $this->assertSame([], $this->redirects);
        $this->assertSame([
            ['setRestoreQuoteLastOrder', [false]],
            ['replaceQuote', [$this->recreatedQuote]],
            ['setQuoteId', [self::RECREATED_QUOTE_ID]],
            ['setLastRealOrderId', [self::INCREMENT_ID]],
            ['restoreQuote', []],
            ['setRestoreQuoteLastOrder', [self::ORDER_ID]],
        ], $this->sessionWrites);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
    }

    /**
     * @param bool $cancelOnBrowserBack
     */
    #[DataProvider('browserBackProvider')]
    public function testCancelledByUserCancelsOnlyWhenConfigured(bool $cancelOnBrowserBack): void
    {
        $instance = $this->buildInstance(['status' => '890', 'cancelOnBrowserBack' => $cancelOnBrowserBack]);

        $this->quoteRecreate->expects($this->once())->method('recreate')->with($this->quote)
            ->willReturn($this->recreatedQuote);
        $this->orderService->expects($cancelOnBrowserBack ? $this->once() : $this->never())
            ->method('cancel')->with($this->order, 890)->willReturn(true);
        $this->order->expects($cancelOnBrowserBack ? $this->never() : $this->once())
            ->method('addCommentToStatusHistory');
        $this->orderRepository->expects($cancelOnBrowserBack ? $this->never() : $this->once())
            ->method('save')->with($this->order);

        $instance->execute();

        $this->assertSame([['checkout', []]], $this->redirects);
        $this->assertSame([self::CANCELLED_ERROR], $this->errorMessages);
    }

    public static function browserBackProvider(): array
    {
        return [
            'leave order pending' => [false],
            'cancel the order'    => [true],
        ];
    }

    public function testPayPerEmailFailureKeepsTheOrderOpen(): void
    {
        $instance = $this->buildInstance(['status' => '490', 'additional' => ['frompayperemail' => '1']]);

        $this->quoteRecreate->expects($this->once())->method('recreate')->willReturn($this->recreatedQuote);
        $this->orderService->expects($this->never())->method('cancel');

        $instance->execute();

        $this->assertSame([['checkout', []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
    }

    public function testInvalidSignatureRedirectsHomeWithoutLoadingTheOrder(): void
    {
        $instance = $this->buildInstance(['status' => '190', 'valid' => false]);

        $this->orderRequestService->expects($this->never())->method('getOrderByRequest');
        $this->lockManager->expects($this->never())->method('lockOrder');
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');
        $this->quoteRecreate->expects($this->never())->method('recreateById');

        $instance->execute();

        $this->assertSame([['/', []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
        $this->assertSame([], $this->sessionWrites);
    }

    public function testEmptyReturnRestoresTheLastOrderQuoteAndRedirectsToTheCart(): void
    {
        $lastOrder = $this->getFakeMock(Order::class)->onlyMethods(['getQuoteId'])->getMock();
        $lastOrder->method('getQuoteId')->willReturn('33');

        $instance = $this->buildInstance(['status' => null, 'data' => [], 'lastRealOrder' => $lastOrder]);

        $this->pushRequest->expects($this->never())->method('validate');
        $this->orderRequestService->expects($this->never())->method('getOrderByRequest');
        $this->quoteRecreate->expects($this->once())->method('recreateById')->with(33);
        $this->orderService->expects($this->never())->method('cancel');

        $instance->execute();

        $this->assertSame([['checkout/cart', []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
    }

    /**
     * @param string   $status
     * @param string   $expectedPath
     * @param int|null $recreatedQuoteId
     * @param array    $expectedErrors
     */
    #[DataProvider('lockNotAcquiredProvider')]
    public function testOrderLockedByAPushLeavesTheOrderAlone(
        string $status,
        string $expectedPath,
        ?int $recreatedQuoteId,
        array $expectedErrors
    ): void {
        $instance = $this->buildInstance(['status' => $status, 'lockAcquired' => false]);

        $this->lockManager->expects($this->never())->method('unlockOrder');
        $this->quote->expects($this->never())->method('load');
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');
        $this->quoteRecreate->expects($recreatedQuoteId ? $this->once() : $this->never())
            ->method('recreateById')->with($recreatedQuoteId);

        $instance->execute();

        $this->assertSame([[$expectedPath, []]], $this->redirects);
        $this->assertSame($expectedErrors, $this->errorMessages);
        $this->assertSame([], $this->sessionWrites);
    }

    public static function lockNotAcquiredProvider(): array
    {
        return [
            'success' => ['190', 'checkout/onepage/success', null, []],
            'failed'  => ['490', 'checkout/cart', self::QUOTE_ID, [self::GENERAL_ERROR]],
        ];
    }

    /**
     * A card return for an earlier transaction of an order the push already handled.
     *
     * @param string $orderState
     * @param string $expectedPath
     */
    #[DataProvider('alreadyHandledProvider')]
    public function testReturnForAnotherTransactionDoesNotTouchTheOrder(string $orderState, string $expectedPath): void
    {
        $instance = $this->buildInstance([
            'status'       => '490',
            'method'       => 'buckaroo_magento2_creditcards',
            'state'        => $orderState,
            'transactions' => 'OTHER-TRANSACTION',
        ]);

        $this->lockManager->expects($this->once())->method('unlockOrder')->with(self::INCREMENT_ID);
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');
        $this->spamLimitService->expects($this->never())->method('updateRateLimiterCount');

        $instance->execute();

        $this->assertSame([[$expectedPath, []]], $this->redirects);
        $this->assertSame([], $this->errorMessages);
    }

    public static function alreadyHandledProvider(): array
    {
        return [
            'order already paid'     => [Order::STATE_PROCESSING, 'checkout/onepage/success'],
            'order already canceled' => [Order::STATE_CANCELED, 'checkout/cart'],
        ];
    }

    /**
     * A signed return for an order that cannot be found sends the shopper to the cart with the
     * general error, without locking, cancelling, recreating or touching the session.
     */
    #[DataProvider('unknownOrderStatusProvider')]
    public function testUnknownOrderRedirectsToTheCartWithoutTouchingAnything(string $status): void
    {
        $instance = $this->buildInstance([
            'status'      => $status,
            'orderLookup' => new BuckarooException(__('There was no order found by transaction Id')),
        ]);
        $this->lockManager->expects($this->never())->method('lockOrder');
        $this->orderService->expects($this->never())->method('cancel');
        $this->quoteRecreate->expects($this->never())->method('recreate');
        $this->quoteRecreate->expects($this->never())->method('recreateById');

        $instance->execute();

        $this->assertSame([['checkout/cart', []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
        $this->assertSame([], $this->successMessages);
        $this->assertSame([], $this->sessionWrites);
    }

    public static function unknownOrderStatusProvider(): array
    {
        return [
            'success status' => ['190'],
            'failed status'  => ['490'],
        ];
    }

    public function testEmptyReturnWithoutLastOrderOnlyRedirectsToTheCart(): void
    {
        $instance = $this->buildInstance(['status' => null, 'data' => []]);
        $this->quoteRecreate->expects($this->never())->method('recreateById');

        $instance->execute();

        $this->assertSame([['checkout/cart', []]], $this->redirects);
        $this->assertSame([self::GENERAL_ERROR], $this->errorMessages);
    }

    public function testReturnWithDataButWithoutStatusIsTreatedAsEmpty(): void
    {
        $instance = $this->buildInstance(['status' => '', 'data' => ['brq_invoicenumber' => '000000123']]);
        $this->pushRequest->expects($this->never())->method('validate');
        $this->orderRequestService->expects($this->never())->method('getOrderByRequest');

        $instance->execute();

        $this->assertSame([['checkout/cart', []]], $this->redirects);
    }

    /**
     * A card return for the payment's own transaction is processed normally, also when the payment holds no
     * original transaction key yet.
     */
    #[DataProvider('cardTransactionKeyProvider')]
    public function testCardReturnForTheOwnTransactionIsProcessed(?string $originalTransactionKey): void
    {
        $instance = $this->buildInstance([
            'status'                 => '190',
            'method'                 => 'buckaroo_magento2_creditcards',
            'originalTransactionKey' => $originalTransactionKey,
        ]);

        $instance->execute();

        $this->assertSame([['checkout/onepage/success', []]], $this->redirects);
        $this->assertContains(['setLastRealOrderId', [self::INCREMENT_ID]], $this->sessionWrites);
    }

    public static function cardTransactionKeyProvider(): array
    {
        return [
            'same transaction'       => ['TRX-1'],
            'no original key stored' => [null],
        ];
    }

    public function testKlarnaReturnStoresTheDataRequestKeyOnce(): void
    {
        $instance = $this->buildInstance([
            'status'      => '190',
            'method'      => 'buckaroo_magento2_klarna',
            'datarequest' => 'DR-KEY-1',
        ]);
        $paymentInfo = [];
        $this->payment->method('setAdditionalInformation')->willReturnCallback(
            function ($key, $value) use (&$paymentInfo) {
                $paymentInfo[$key] = $value;
                return $this->payment;
            }
        );
        $this->orderRepository->expects($this->once())->method('save')->with($this->order);

        $instance->execute();

        $this->assertSame('DR-KEY-1', $this->order->getData('buckaroo_datarequest_key'));
        $this->assertSame('DR-KEY-1', $paymentInfo['buckaroo_datarequest_key']);
    }

    /**
     * @param string      $method
     * @param string|null $dataRequest
     * @param string|null $storedKey
     */
    #[DataProvider('dataRequestKeyKeptProvider')]
    public function testDataRequestKeyIsNotStored(string $method, ?string $dataRequest, ?string $storedKey): void
    {
        $instance = $this->buildInstance([
            'status'      => '190',
            'method'      => $method,
            'datarequest' => $dataRequest,
            'orderData'   => ['buckaroo_datarequest_key' => $storedKey],
        ]);
        $this->orderRepository->expects($this->never())->method('save');

        $instance->execute();

        $this->assertSame($storedKey, $this->order->getData('buckaroo_datarequest_key'));
    }

    public static function dataRequestKeyKeptProvider(): array
    {
        return [
            'other method'      => ['buckaroo_magento2_ideal', 'DR-KEY-1', null],
            'no key returned'   => ['buckaroo_magento2_klarna', null, null],
            'key already saved' => ['buckaroo_magento2_klarna', 'DR-KEY-2', 'DR-KEY-1'],
        ];
    }

    /**
     * @param array       $postData
     * @param array       $flags
     * @param string|null $storedNumber
     * @param bool        $saved
     */
    #[DataProvider('klarnaReservationProvider')]
    public function testKlarnaKpReservationNumberIsStoredForANewReserve(
        array $postData,
        array $flags,
        ?string $storedNumber,
        bool $saved
    ): void {
        $instance = $this->buildInstance([
            'status'            => '190',
            'postData'          => $postData,
            'flags'             => $flags,
            'reservationNumber' => 'RES-1',
            'orderData'         => ['buckaroo_reservation_number' => $storedNumber],
        ]);
        $this->reservationNumberStore->expects($saved ? $this->once() : $this->never())
            ->method('save')->with($this->order, 'RES-1');

        $instance->execute();
    }

    public static function klarnaReservationProvider(): array
    {
        $klarnaKp = ['primary_service' => 'KlarnaKp'];
        $reserve = ['service_action_from_magento' => 'reserve'];
        return [
            'new reserve'           => [$klarnaKp, $reserve, null, true],
            'number already stored' => [$klarnaKp, $reserve, 'RES-0', false],
            'not a reserve'         => [$klarnaKp, ['service_action_from_magento' => 'pay'], null, false],
            'other service'         => [['primary_service' => 'Ideal'], $reserve, null, false],
        ];
    }

    /**
     * @param string      $status
     * @param array       $flags
     * @param string      $accountEmail
     * @param string|null $methodEmail
     * @param bool        $emailSent
     * @param bool        $expectEmail
     */
    #[DataProvider('orderEmailProvider')]
    public function testOrderEmailIsSentUnlessAlreadySentOrAMagentoReserve(
        string $status,
        array $flags,
        string $accountEmail,
        ?string $methodEmail,
        bool $emailSent,
        bool $expectEmail
    ): void {
        $instance = $this->buildInstance([
            'status'                 => $status,
            'postData'               => ['primary_service' => 'KlarnaKp'],
            'flags'                  => $flags,
            'reservationNumber'      => 'RES-1',
            'orderConfirmationEmail' => $accountEmail,
            'methodOrderEmail'       => $methodEmail,
            'orderData'              => ['email_sent' => $emailSent],
        ]);
        $this->orderStatusFactory->method('get')->willReturn(null);
        $this->orderRequestService->expects($expectEmail ? $this->once() : $this->never())
            ->method('sendOrderEmail')->with($this->order, true);

        $instance->execute();
    }

    public static function orderEmailProvider(): array
    {
        $reserve = ['service_action_from_magento' => 'reserve'];
        $magentoReserve = $reserve + ['initiated_by_magento' => 1];
        return [
            'account setting'               => ['190', [], '1', null, false, true],
            'payment method setting'        => ['190', [], '0', '1', false, true],
            'both settings off'             => ['190', [], '0', '0', false, false],
            'already sent'                  => ['190', [], '1', null, true, false],
            'reserve placed by magento'     => ['190', $magentoReserve, '1', null, false, false],
            'reserve placed elsewhere'      => ['190', $reserve, '1', null, false, true],
            'pending reserve by magento'    => ['791', $magentoReserve, '1', null, false, true],
            'magento flag, not a reserve'   => ['190', ['initiated_by_magento' => 1], '1', null, false, true],
        ];
    }

    /**
     * @param array $postData
     * @param bool  $recorded
     */
    #[DataProvider('applePayProvider')]
    public function testApplePayTestReturnRecordsTheOrderStatusInTheSession(array $postData, bool $recorded): void
    {
        $instance = $this->buildInstance(['status' => '190', 'postData' => $postData]);
        $this->order->method('getStatus')->willReturn('processing');

        $instance->execute();

        $this->assertSame(
            $recorded,
            in_array(['setLastOrderStatus', ['processing']], $this->sessionWrites, true)
        );
    }

    public static function applePayProvider(): array
    {
        $applePay = ['payment_method' => 'applepay', 'status_code' => '190', 'test' => 'true'];
        return [
            'apple pay test return' => [$applePay, true],
            'live return'           => [['test' => 'false'] + $applePay, false],
            'other method'          => [['payment_method' => 'ideal'] + $applePay, false],
            'other status'          => [['status_code' => '791'] + $applePay, false],
        ];
    }

    public function testBrowserBackLeftPendingAddsAHiddenComment(): void
    {
        $instance = $this->buildInstance(['status' => '890', 'cancelOnBrowserBack' => false]);
        $this->order->expects($this->once())->method('addCommentToStatusHistory')->with(
            $this->callback(fn ($comment) => (string)$comment
                === 'Customer returned using browser back button. Order left pending for push notification.'),
            false,
            false
        );

        $instance->execute();
    }

    /**
     * @param string $failureRedirect
     * @param string $expectedUrl
     */
    #[DataProvider('fragmentUrlProvider')]
    public function testFailureFragmentUrlIsMadeAbsolute(string $failureRedirect, string $expectedUrl): void
    {
        $instance = $this->buildInstance(['status' => '490', 'failureRedirect' => $failureRedirect]);
        $this->orderService->method('cancel')->willReturn(true);

        $instance->execute();

        $this->assertSame([$expectedUrl], $this->rawRedirects);
    }

    public static function fragmentUrlProvider(): array
    {
        return [
            'leading slash'          => ['/checkout#payment', 'https://shop.test/checkout#payment'],
            'fragment with a hash'   => ['checkout/#payment#step', 'https://shop.test/checkout#payment#step'],
            'absolute url'           => ['https://pay.test/checkout/#payment', 'https://pay.test/checkout#payment'],
        ];
    }

    public function testPublicHooksUsedByPlugins(): void
    {
        $instance = $this->buildInstance(['status' => '190']);

        $this->assertFalse($instance->getSkipHandleFailedRecreate());
        $this->assertTrue($instance->setSkipHandleFailedRecreate());
        $instance->addErrorMessage('first');
        $instance->addErrorMessageByStatus(890);
        $instance->addErrorMessageByStatus(999);
        $instance->addSuccessMessage('done');
        $instance->handleProcessedResponse('checkout/cart', ['_query' => ['a' => 1]]);

        $this->assertSame(['first', self::CANCELLED_ERROR, self::GENERAL_ERROR], $this->errorMessages);
        $this->assertSame(['done'], $this->successMessages);
        $this->assertSame([['checkout/cart', ['_query' => ['a' => 1]]]], $this->redirects);
    }

    public function testPendingReturnSendsTheOrderEmail(): void
    {
        $instance = $this->buildInstance(['status' => '791', 'orderConfirmationEmail' => '1']);
        $this->orderStatusFactory->method('get')->willReturn(null);
        $this->orderRequestService->expects($this->once())->method('sendOrderEmail')->with($this->order, true);

        $instance->execute();
    }

    /**
     * A card return still waiting on the shopper for an order a push already paid goes to the success page.
     *
     * @param string     $state
     * @param string     $status
     * @param array      $expectedRedirects
     * @param bool       $sessionUpdated
     * @param bool       $skipped
     */
    #[DataProvider('waitingOnConsumerProvider')]
    public function testCardReturnWaitingOnConsumer(
        string $state,
        string $status,
        array $expectedRedirects,
        bool $sessionUpdated,
        bool $skipped
    ): void {
        $instance = $this->buildInstance([
            'status' => $status,
            'method' => 'buckaroo_magento2_creditcards',
            'state'  => $state,
        ]);

        $instance->execute();

        $this->assertSame($expectedRedirects, $this->redirects);
        $this->assertSame(
            $sessionUpdated,
            in_array(['setLastRealOrderId', [self::INCREMENT_ID]], $this->sessionWrites, true)
        );
        $this->assertSame($skipped, $instance->skipWaitingOnConsumerForProcessingOrder());
    }

    public static function waitingOnConsumerProvider(): array
    {
        return [
            'paid order, waiting'    => [Order::STATE_PROCESSING, '792', [['checkout/onepage/success', []]], false, true],
            'pending order, waiting' => [Order::STATE_PENDING_PAYMENT, '792', [], false, false],
            'paid order, success'    => [Order::STATE_PROCESSING, '190', [['checkout/onepage/success', []]], true, false],
        ];
    }

    /**
     * @param array $overrides
     *
     * @return Process
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function buildInstance(array $overrides): Process
    {
        $this->scenario = array_merge([
            'status'                    => '190',
            'data'                      => ['brq_statuscode' => $overrides['status'] ?? '190'],
            'valid'                     => true,
            'lockAcquired'              => true,
            'method'                    => 'buckaroo_magento2_ideal',
            'state'                     => Order::STATE_PENDING_PAYMENT,
            'transactions'              => 'TRX-1',
            'invoiceHandling'           => InvoiceHandlingOptions::PAYMENT,
            'additional'                => [],
            'successRedirect'           => '',
            'failureRedirect'           => '',
            'failureRedirectToCheckout' => false,
            'cancelOnBrowserBack'       => false,
            'orderConfirmationEmail'    => '0',
            'lastRealOrder'             => null,
            'orderLookup'               => null,
            'postData'                  => [],
            'flags'                     => [],
            'reservationNumber'         => null,
            'datarequest'               => null,
            'methodOrderEmail'          => null,
            'orderData'                 => [],
            'originalTransactionKey'    => 'TRX-1',
        ], $overrides);

        $store = $this->getFakeMock(StoreInterface::class)->getMock();

        $this->methodInstance = $this->getFakeMock(MethodInterface::class)->getMock();
        $this->methodInstance->method('getConfigData')->willReturnMap([
            ['order_email', $store, $this->scenario['methodOrderEmail']],
        ]);

        $this->payment = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getMethodInstance', 'getAdditionalInformation', 'setAdditionalInformation'])
            ->getMock();
        $this->payment->method('getMethod')->willReturn($this->scenario['method']);
        $this->payment->method('getMethodInstance')->willReturn($this->methodInstance);
        $this->payment->method('getAdditionalInformation')->willReturnMap([
            ['buckaroo_original_transaction_key', $this->scenario['originalTransactionKey']],
            [InvoiceHandlingOptions::INVOICE_HANDLING, $this->scenario['invoiceHandling']],
        ]);

        $this->order = $this->getFakeMock(Order::class)
            ->onlyMethods([
                'getId', 'getIncrementId', 'getQuoteId', 'getPayment', 'getState', 'getStore',
                'canInvoice', 'setStatus', 'addCommentToStatusHistory', 'getStatus',
            ])
            ->getMock();
        $this->order->method('getId')->willReturn(self::ORDER_ID);
        $this->order->method('getIncrementId')->willReturn(self::INCREMENT_ID);
        $this->order->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->order->method('getState')->willReturn($this->scenario['state']);
        $this->order->method('getStore')->willReturn($store);
        $this->order->method('canInvoice')->willReturn(true);
        $this->order->setData($this->scenario['orderData']);

        $this->quote = $this->getFakeMock(Quote::class)->onlyMethods(['load', 'setReservedOrderId'])->getMock();
        $this->recreatedQuote = $this->getFakeMock(Quote::class)->onlyMethods(['setIsActive', 'getId'])->getMock();
        $this->recreatedQuote->method('getId')->willReturn(self::RECREATED_QUOTE_ID);

        $this->pushRequest = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $this->pushRequest->method('getData')->willReturn($this->scenario['data']);
        $this->pushRequest->method('getOriginalRequest')->willReturn([]);
        $this->pushRequest->method('getStatusCode')->willReturn($this->scenario['status']);
        $this->pushRequest->method('validate')->willReturn($this->scenario['valid']);
        $this->pushRequest->method('getTransactions')->willReturn($this->scenario['transactions']);
        $this->pushRequest->method('hasPostData')->willReturnCallback(
            fn ($name, $value) => ($this->scenario['postData'][$name] ?? null) === $value
        );
        $this->pushRequest->method('hasAdditionalInformation')->willReturnCallback(
            fn ($name, $value) => array_key_exists($name, $this->scenario['flags'])
                && $this->scenario['flags'][$name] === $value
        );
        $this->pushRequest->method('getServiceKlarnakpReservationnumber')
            ->willReturn($this->scenario['reservationNumber']);
        $this->pushRequest->method('getDatarequest')->willReturn($this->scenario['datarequest']);
        $this->pushRequest->method('getAdditionalInformation')
            ->willReturnCallback(fn ($key) => $this->scenario['additional'][$key] ?? null);

        $requestPushFactory = $this->getFakeMock(RequestPushFactory::class)
            ->onlyMethods(['createFromFormPost'])
            ->getMock();
        $requestPushFactory->method('createFromFormPost')->willReturn($this->pushRequest);

        $this->orderRequestService = $this->getFakeMock(OrderRequestService::class)
            ->onlyMethods(['getOrderByRequest', 'sendOrderEmail'])
            ->getMock();
        if ($this->scenario['orderLookup'] instanceof \Exception) {
            $this->orderRequestService->method('getOrderByRequest')->willThrowException($this->scenario['orderLookup']);
        } else {
            $this->orderRequestService->method('getOrderByRequest')->willReturn($this->order);
        }

        $this->lockManager = $this->getFakeMock(LockManagerWrapper::class)
            ->onlyMethods(['lockOrder', 'unlockOrder'])
            ->getMock();
        $this->lockManager->method('lockOrder')->willReturn($this->scenario['lockAcquired']);

        $accountConfig = $this->getFakeMock(Account::class)
            ->onlyMethods([
                'getSuccessRedirect', 'getFailureRedirect', 'getFailureRedirectToCheckout',
                'getCancelOnBrowserBack', 'getOrderConfirmationEmail',
            ])
            ->getMock();
        $accountConfig->method('getSuccessRedirect')->willReturn($this->scenario['successRedirect']);
        $accountConfig->method('getFailureRedirect')->willReturn($this->scenario['failureRedirect']);
        $accountConfig->method('getFailureRedirectToCheckout')
            ->willReturn($this->scenario['failureRedirectToCheckout']);
        $accountConfig->method('getCancelOnBrowserBack')->willReturn($this->scenario['cancelOnBrowserBack']);
        $accountConfig->method('getOrderConfirmationEmail')->willReturn($this->scenario['orderConfirmationEmail']);

        $this->orderService = $this->getFakeMock(OrderService::class)->onlyMethods(['cancel'])->getMock();
        $this->quoteRecreate = $this->getFakeMock(Recreate::class)
            ->onlyMethods(['recreate', 'recreateById'])
            ->getMock();
        $this->quoteRecreate->method('recreate')->willReturn($this->recreatedQuote);
        $this->orderRepository = $this->getFakeMock(OrderRepositoryInterface::class)->getMock();
        $this->cartRepository = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $this->spamLimitService = $this->getFakeMock(SpamLimitService::class)
            ->onlyMethods(['updateRateLimiterCount', 'setMaxAttemptsFlags'])
            ->getMock();
        $this->orderStatusFactory = $this->getFakeMock(OrderStatusFactory::class)->onlyMethods(['get'])->getMock();
        $this->reservationNumberStore = $this->getFakeMock(ReservationNumberStore::class)
            ->onlyMethods(['save'])->getMock();

        $eventManager = $this->getFakeMock(EventManager::class)->getMock();
        $eventManager->method('dispatch')->willReturnCallback(function ($name) {
            $this->events[] = $name;
        });

        return $this->getInstance([
            'context'             => $this->buildContext(),
            'quote'               => $this->quote,
            'accountConfig'       => $accountConfig,
            'orderRequestService' => $this->orderRequestService,
            'orderStatusFactory'  => $this->orderStatusFactory,
            'checkoutSession'     => $this->buildCheckoutSession(),
            'orderService'        => $this->orderService,
            'eventManager'        => $eventManager,
            'quoteRecreate'       => $this->quoteRecreate,
            'requestPushFactory'  => $requestPushFactory,
            'lockManager'         => $this->lockManager,
            'spamLimitService'    => $this->spamLimitService,
            'orderRepository'     => $this->orderRepository,
            'cartRepository'      => $this->cartRepository,
            'reservationNumberStore' => $this->reservationNumberStore,
        ]);
    }

    /**
     * Checkout session that records every write, in order
     *
     * @return CheckoutSession
     */
    private function buildCheckoutSession(): CheckoutSession
    {
        $session = $this->getFakeMock(CheckoutSession::class)
            ->onlyMethods(['__call', 'getLastRealOrder', 'replaceQuote', 'setQuoteId', 'restoreQuote'])
            ->getMock();
        $session->method('getLastRealOrder')->willReturn($this->scenario['lastRealOrder']);
        $session->method('__call')->willReturnCallback(function ($method, $args) use ($session) {
            if (strpos($method, 'get') === 0) {
                return null;
            }
            $this->sessionWrites[] = [$method, $args];
            return $session;
        });
        foreach (['replaceQuote', 'setQuoteId', 'restoreQuote'] as $method) {
            $session->method($method)->willReturnCallback(function (...$args) use ($method, $session) {
                $this->sessionWrites[] = [$method, $args];
                return $method === 'restoreQuote' ? true : $session;
            });
        }

        return $session;
    }

    /**
     * @return Context
     */
    private function buildContext(): Context
    {
        $response = $this->getFakeMock(HttpResponse::class)->onlyMethods(['setRedirect'])->getMock();
        $response->method('setRedirect')->willReturnCallback(function ($url) use ($response) {
            $this->rawRedirects[] = $url;
            return $response;
        });

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMock();
        $redirect->method('redirect')->willReturnCallback(function ($target, $path, $args = []) use ($response) {
            $this->assertSame($response, $target);
            $this->redirects[] = [$path, $args];
        });

        $messageManager = $this->getFakeMock(MessageManager::class)->getMock();
        $messageManager->method('addErrorMessage')->willReturnCallback(function ($message) use ($messageManager) {
            $this->errorMessages[] = (string)$message;
            return $messageManager;
        });
        $messageManager->method('addSuccessMessage')->willReturnCallback(function ($message) use ($messageManager) {
            $this->successMessages[] = (string)$message;
            return $messageManager;
        });

        $url = $this->getFakeMock(UrlInterface::class)->getMock();
        $url->method('getUrl')->willReturnMap([
            ['checkout', ['_query' => ['bk_e' => 1]], 'https://shop.test/checkout/?bk_e=1'],
        ]);
        $url->method('getBaseUrl')->willReturn('https://shop.test/');

        $context = $this->getFakeMock(Context::class)
            ->onlyMethods([
                'getRequest', 'getResponse', 'getRedirect', 'getMessageManager', 'getUrl', 'getObjectManager',
            ])
            ->getMock();
        $context->method('getResponse')->willReturn($response);
        $context->method('getRedirect')->willReturn($redirect);
        $context->method('getMessageManager')->willReturn($messageManager);
        $context->method('getUrl')->willReturn($url);
        $context->method('getObjectManager')->willReturn($this->getFakeMock(ObjectManagerInterface::class)->getMock());

        return $context;
    }
}
