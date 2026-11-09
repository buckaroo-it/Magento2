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

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Test\Unit\Stubs\MethodConfigProviderStub;
use PHPUnit\Framework\TestCase;

class OrderStatusFactoryTest extends TestCase
{
    /**
     * @var Account|\PHPUnit\Framework\MockObject\MockObject
     */
    private $account;

    /**
     * @var Factory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configProviderFactory;

    /**
     * @var OrderStatusFactory
     */
    private $orderStatusFactory;

    protected function setUp(): void
    {
        $this->account = $this->createMock(Account::class);
        $this->configProviderFactory = $this->createMock(Factory::class);
        $this->orderStatusFactory = new OrderStatusFactory($this->account, $this->configProviderFactory);
    }

    /**
     * getStoreId() comes off the sales_order row as a string; the factory has to hand a real int
     * to the config layer.
     */
    private function order(string $storeId, string $method = 'buckaroo_magento2_ideal'): Order
    {
        $methodInstance = $this->getMockBuilder(BuckarooAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $methodInstance->method('getCode')->willReturn($method);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn($storeId);

        return $order;
    }

    /**
     * The push and the redirect controller both run outside the order's store, so the status has
     * to be resolved with the order's store id rather than the ambient one.
     */
    public function testAccountStatusIsResolvedWithTheOrderStoreIdAsAnInt(): void
    {
        $this->configProviderFactory->method('has')->willReturn(false);

        $this->account->expects($this->once())
            ->method('getOrderStatusSuccess')
            ->with(null, 2)
            ->willReturn('buckaroo_magento2_new');

        $this->assertSame(
            'buckaroo_magento2_new',
            $this->orderStatusFactory->get(BuckarooStatusCode::SUCCESS, $this->order('2'))
        );
    }

    public function testFailedStatusIsResolvedWithTheOrderStore(): void
    {
        $this->configProviderFactory->method('has')->willReturn(false);

        $this->account->expects($this->once())->method('getOrderStatusFailed')->with(null, 2)->willReturn('holded');

        $this->assertSame(
            'holded',
            $this->orderStatusFactory->get(BuckarooStatusCode::FAILED, $this->order('2'))
        );
    }

    public function testPendingStatusIsResolvedWithTheOrderStore(): void
    {
        $this->configProviderFactory->method('has')->willReturn(false);

        $this->account->expects($this->once())->method('getOrderStatusPending')->with(2)->willReturn('pending_payment');

        $this->assertSame(
            'pending_payment',
            $this->orderStatusFactory->get(BuckarooStatusCode::PAYMENT_ON_HOLD, $this->order('2'))
        );
    }

    /**
     * A method level status wins over the account level - and it too has to be read in the order's
     * store, not the ambient one.
     */
    public function testMethodStatusOverridesTheAccountStatusAndIsAlsoStoreScoped(): void
    {
        $methodConfig = $this->getMockBuilder(MethodConfigProviderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getActiveStatus', 'getOrderStatusSuccess'])
            ->getMock();
        $methodConfig->expects($this->once())->method('getActiveStatus')->with(2)->willReturn(true);
        $methodConfig->expects($this->once())->method('getOrderStatusSuccess')->with(2)->willReturn('fraud');

        $this->configProviderFactory->method('has')->willReturn(true);
        $this->configProviderFactory->method('get')->willReturn($methodConfig);

        $this->account->expects($this->never())->method('getOrderStatusSuccess');

        $this->assertSame(
            'fraud',
            $this->orderStatusFactory->get(BuckarooStatusCode::SUCCESS, $this->order('2'))
        );
    }

    public function testFallsBackToTheAccountStatusWhenTheMethodStatusIsInactive(): void
    {
        $methodConfig = $this->getMockBuilder(MethodConfigProviderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getActiveStatus', 'getOrderStatusSuccess'])
            ->getMock();
        $methodConfig->method('getActiveStatus')->with(2)->willReturn(false);

        $this->configProviderFactory->method('has')->willReturn(true);
        $this->configProviderFactory->method('get')->willReturn($methodConfig);

        $this->account->expects($this->once())->method('getOrderStatusSuccess')->with(null, 2)->willReturn('processing');

        $this->assertSame(
            'processing',
            $this->orderStatusFactory->get(BuckarooStatusCode::SUCCESS, $this->order('2'))
        );
    }

    public function testAnUnknownStatusCodeYieldsFalse(): void
    {
        $this->configProviderFactory->method('has')->willReturn(false);

        $this->assertFalse($this->orderStatusFactory->get(123456, $this->order('2')));
    }
}
