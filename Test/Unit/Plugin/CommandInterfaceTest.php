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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\Applepay;
use Buckaroo\Magento2\Plugin\CommandInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as MagentoCommandInterface;

/**
 * No-op Log stub: avoids Log::__destruct() calling $this->mail->mailMessage() (which
 * crashes when the constructor is disabled/bypassed). Extending Log satisfies the type hint.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration
class LogStub extends \Buckaroo\Magento2\Logging\Log
{
    public function __construct() {} // phpcs:ignore
    public function addDebug(string $message): bool { return true; } // phpcs:ignore
    public function __destruct() {} // phpcs:ignore
}

/**
 * Unit tests for Plugin\CommandInterface.
 *
 * Note: this test file uses createMock() throughout for PHPUnit 10 compatibility.
 * The existing test suite uses getFakeMock()->setMethods() which requires PHPUnit <10.
 */
class CommandInterfaceTest extends BaseTest
{
    protected $instanceClass = CommandInterface::class;

    /**
     * Build the plugin instance with fully-stubbed dependencies.
     * Uses direct instantiation to avoid Magento's ObjectManager overriding mocks.
     */
    private function buildInstance(?Data $helper = null): CommandInterface
    {
        $helper  = $helper  ?? $this->createMock(Data::class);
        $factory = $this->createMock(Factory::class);
        // LogStub extends Log with a no-op constructor and destructor to avoid
        // Log::__destruct() calling $this->mail->mailMessage() on a null property.
        return new CommandInterface($factory, new LogStub(), $helper);
    }

    /**
     * Build an Order stub with preset getState() / getStatus() return values.
     */
    private function orderWithState(string $state, string $status): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn($state);
        $order->method('getStatus')->willReturn($status);

        return $order;
    }

    /**
     * Build a MethodInterface stub for a given payment method code and payment action.
     */
    private function methodStub(string $code, ?string $paymentAction = 'order'): MethodInterface
    {
        $method = $this->getMockBuilder(MethodInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode', 'getConfigPaymentAction'])
            ->getMockForAbstractClass();
        $method->method('getCode')->willReturn($code);
        $method->method('getConfigPaymentAction')->willReturn($paymentAction);

        return $method;
    }

    /**
     * Build an OrderPaymentInterface stub that also exposes getMethodInstance().
     * Optionally sets the push-capture flag via getAdditionalInformation().
     */
    private function paymentStub(string $methodCode, bool $withPushFlag = false): OrderPaymentInterface
    {
        $payment = $this->getMockBuilder(OrderPaymentInterface::class)
            ->addMethods(['getMethodInstance'])
            ->getMockForAbstractClass();
        $payment->method('getMethodInstance')->willReturn($this->methodStub($methodCode));
        $payment->method('getAdditionalInformation')
            ->with('buckaroo_push_capture_in_progress')
            ->willReturn($withPushFlag ? true : false);

        return $payment;
    }

    // -----------------------------------------------------------------------
    // aroundExecute: Apple Pay flag-based guard (regression tests for BP-1481)
    // -----------------------------------------------------------------------

    /**
     * REGRESSION (Ticket 1): When Push.php::saveInvoice() sets the push-capture flag and
     * calls registerCaptureNotification(), aroundExecute() must NOT reset the order state.
     * Without this guard the order reverts to STATE_NEW/pending after a successful push,
     * creating a spurious "Pending | Customer Notified" history entry (regression 72fa364d).
     */
    public function testAroundExecuteApplePayWithFlagSkipsStateUpdate(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getOrderStatusByState');

        $order = $this->orderWithState(Order::STATE_PROCESSING, 'processing');
        $order->expects($this->never())->method('setState');
        $order->expects($this->never())->method('setStatus');

        $proceed = static function () {
            return 'done';
        };

        $commandInterface = $this->createMock(MagentoCommandInterface::class);
        $instance = $this->buildInstance($helper);

        $result = $instance->aroundExecute(
            $commandInterface,
            $proceed,
            $this->paymentStub(Applepay::PAYMENT_METHOD_CODE, true),
            100.0,
            $order
        );

        $this->assertSame('done', $result);
    }

    /**
     * Apple Pay at checkout (no push-capture flag) must still go through normal
     * state normalisation. The guard is conditional, NOT a blanket skip.
     */
    public function testAroundExecuteApplePayWithoutFlagRunsStateUpdate(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getOrderStatusByState')->willReturn('pending');

        // Order is in STATE_PROCESSING at checkout (Apple Pay uses payment_action="order")
        $order = $this->orderWithState(Order::STATE_PROCESSING, 'processing');
        // No push flag → updateOrderStateAndStatus runs → setState IS called
        $order->expects($this->once())->method('setState')->with(Order::STATE_NEW);

        $proceed = static function () {
            return 'done';
        };

        $commandInterface = $this->createMock(MagentoCommandInterface::class);
        $instance = $this->buildInstance($helper);

        $instance->aroundExecute(
            $commandInterface,
            $proceed,
            $this->paymentStub(Applepay::PAYMENT_METHOD_CODE, false),
            100.0,
            $order
        );
    }

    // -----------------------------------------------------------------------
    // updateOrderStateAndStatus: direct unit tests
    // -----------------------------------------------------------------------

    /**
     * Apple Pay in STATE_NEW (the common checkout state) must have its status
     * normalised. setState should NOT be called (already in target state), but
     * setStatus IS called when current status matches the default.
     */
    public function testApplePayInStateNewDoesRunUpdate(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getOrderStatusByState')->willReturn('pending');

        $order = $this->orderWithState(Order::STATE_NEW, 'pending');
        // Already in STATE_NEW → setState should NOT be called
        $order->expects($this->never())->method('setState');
        // currentStatus === defaultStatus ('pending') → setStatus IS called
        $order->expects($this->once())->method('setStatus')->with('pending');

        $instance = $this->buildInstance($helper);
        $this->invokeArgs('updateOrderStateAndStatus', [$order, $this->methodStub(Applepay::PAYMENT_METHOD_CODE)], $instance);
    }

    // -----------------------------------------------------------------------
    // Ensure Apple Pay flag guard does not bleed into other methods
    // -----------------------------------------------------------------------

    /**
     * Non-Apple Pay Buckaroo method in STATE_PROCESSING must still be reset to STATE_NEW.
     */
    public function testNonApplePayInProcessingResetsState(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getOrderStatusByState')->willReturn('pending');

        $order = $this->orderWithState(Order::STATE_PROCESSING, 'processing');
        $order->expects($this->once())->method('setState')->with(Order::STATE_NEW);

        $instance = $this->buildInstance($helper);
        $this->invokeArgs('updateOrderStateAndStatus', [$order, $this->methodStub('buckaroo_magento2_ideal')], $instance);
    }

    /**
     * Custom status ('processing' !== 'pending') must be preserved after state reset.
     */
    public function testNonApplePayPreservesCustomStatus(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getOrderStatusByState')->willReturn('pending');

        $order = $this->orderWithState(Order::STATE_PROCESSING, 'processing');
        $order->expects($this->once())->method('setState')->with(Order::STATE_NEW);
        $order->expects($this->never())->method('setStatus');

        $instance = $this->buildInstance($helper);
        $this->invokeArgs('updateOrderStateAndStatus', [$order, $this->methodStub('buckaroo_magento2_creditcard')], $instance);
    }

    // -----------------------------------------------------------------------
    // aroundExecute guard tests
    // -----------------------------------------------------------------------

    /**
     * aroundExecute must skip all state manipulation for non-Buckaroo methods.
     */
    public function testAroundExecuteSkipsNonBuckarooMethod(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getOrderStatusByState');

        $order = $this->orderWithState(Order::STATE_NEW, 'pending');
        $order->expects($this->never())->method('setState');

        $proceed = static function () {
            return 'done';
        };

        $payment = $this->getMockBuilder(OrderPaymentInterface::class)
            ->addMethods(['getMethodInstance'])
            ->getMockForAbstractClass();
        $payment->method('getMethodInstance')->willReturn($this->methodStub('paypal_express', 'authorize'));

        $commandInterface = $this->createMock(MagentoCommandInterface::class);

        $instance = $this->buildInstance($helper);
        $result = $instance->aroundExecute($commandInterface, $proceed, $payment, 100.0, $order);

        $this->assertSame('done', $result);
    }

    /**
     * aroundExecute must skip state manipulation when payment action is not configured.
     */
    public function testAroundExecuteSkipsWhenNoPaymentAction(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->never())->method('getOrderStatusByState');

        $order = $this->orderWithState(Order::STATE_NEW, 'pending');
        $order->expects($this->never())->method('setState');

        $proceed = static function () {
            return 'done';
        };

        $payment = $this->getMockBuilder(OrderPaymentInterface::class)
            ->addMethods(['getMethodInstance'])
            ->getMockForAbstractClass();
        $payment->method('getMethodInstance')->willReturn($this->methodStub('buckaroo_magento2_ideal', null));

        $commandInterface = $this->createMock(MagentoCommandInterface::class);

        $instance = $this->buildInstance($helper);
        $result = $instance->aroundExecute($commandInterface, $proceed, $payment, 50.0, $order);

        $this->assertSame('done', $result);
    }
}
