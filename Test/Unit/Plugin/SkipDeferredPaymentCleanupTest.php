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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Buckaroo\Magento2\Model\MagentoOrderCleanupScope;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;

class SkipDeferredPaymentCleanupTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Plugin\SkipDeferredPaymentCleanup';

    private const ORDER_ID = 42;

    private $cleanupScope;
    private $orderRepositoryMock;
    private $transferConfigMock;
    private $payPerEmailConfigMock;
    private $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        // The real scope object: its behaviour is what decides whether the guard engages at all.
        $this->cleanupScope = new MagentoOrderCleanupScope();
        $this->orderRepositoryMock = $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock();
        $this->transferConfigMock = $this->getFakeMock(Transfer::class)->getMock();
        $this->payPerEmailConfigMock = $this->getFakeMock(PayPerEmail::class)->getMock();
        $this->loggerMock = $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance([
            'cleanupScope' => $this->cleanupScope,
            'orderRepository' => $this->orderRepositoryMock,
            'transferConfig' => $this->transferConfigMock,
            'payPerEmailConfig' => $this->payPerEmailConfigMock,
            'logger' => $this->loggerMock,
        ] + $args);
    }

    /**
     * @param string $method
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function orderWithMethod(string $method, int $ageInDays = 0)
    {
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethod')->willReturn($method);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn('900000001');
        $orderMock->method('getCreatedAt')->willReturn(
            (new \DateTimeImmutable())->modify(sprintf('-%d day', $ageInDays))->format('Y-m-d H:i:s')
        );

        return $orderMock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function orderManagementMock()
    {
        return $this->getFakeMock('Magento\Sales\Api\OrderManagementInterface')->getMock();
    }

    /**
     * Outside the cleanup cron nothing may be intercepted, or an administrator pressing Cancel would
     * silently do nothing while Magento still reports success.
     */
    public function testCancellationOutsideTheCleanupCronAlwaysProceeds(): void
    {
        $instance = $this->getInstance();

        // Not even an order lookup should happen when the cron is not the caller.
        $this->orderRepositoryMock->expects($this->never())->method('get');

        $proceedCalled = false;
        $proceed = function ($id) use (&$proceedCalled) {
            $proceedCalled = true;
            $this->assertSame(self::ORDER_ID, $id);
            return true;
        };

        $result = $instance->aroundCancel($this->orderManagementMock(), $proceed, self::ORDER_ID);

        $this->assertTrue($proceedCalled, 'the cancellation must be passed through');
        $this->assertTrue($result);
    }

    public static function managedExpiryProvider(): array
    {
        return [
            // Buckaroo's cron will act, so Magento's cleanup must not.
            'bank transfer with a due date is protected' => ['buckaroo_magento2_transfer', 7, true, 7, false],
            'pay per email with cancel cron on is protected' => ['buckaroo_magento2_payperemail', 7, true, 7, false],
            // Buckaroo's cron would not act, so leaving the order alone would leak it forever.
            'bank transfer without a due date is not protected' => ['buckaroo_magento2_transfer', 0, true, 7, true],
            'pay per email with the cancel cron off is not protected' =>
                ['buckaroo_magento2_payperemail', 7, false, 7, true],
            'pay per email without expire days is not protected' => ['buckaroo_magento2_payperemail', 7, true, 0, true],
            // Any other method is Magento's business as before.
            'ideal is not protected' => ['buckaroo_magento2_ideal', 7, true, 7, true],
            'a non buckaroo method is not protected' => ['checkmo', 7, true, 7, true],
        ];
    }

    public static function expiryWindowProvider(): array
    {
        // due date 7 + EXPIRY_WINDOW_GRACE_DAYS 7 = a 14 day window for Buckaroo's cron.
        return [
            'freshly placed is protected'                      => [0, false],
            'past the due date but inside the window'          => [10, false],
            'just inside the window'                           => [13, false],
            // Beyond the window Buckaroo's cron ignores it for good, so it must not stay shielded.
            'beyond the window is handed back to Magento'      => [20, true],
            'far beyond the window is handed back to Magento'  => [120, true],
        ];
    }

    /**
     * @param int  $ageInDays
     * @param bool $expectsProceed
     */
    #[DataProvider('expiryWindowProvider')]
    public function testOrdersOlderThanBuckaroosWindowAreNotLeftWaitingForever(
        int $ageInDays,
        bool $expectsProceed
    ): void {
        $instance = $this->getInstance();

        $this->transferConfigMock->method('getDueDate')->willReturn(7);
        $this->orderRepositoryMock->method('get')
            ->willReturn($this->orderWithMethod('buckaroo_magento2_transfer', $ageInDays));

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return true;
        };

        $this->cleanupScope->run(
            fn () => $instance->aroundCancel($this->orderManagementMock(), $proceed, self::ORDER_ID)
        );

        $this->assertSame($expectsProceed, $proceedCalled);
    }

    /**
     * @param string $method
     * @param int    $transferDueDate
     * @param bool   $ppeCronEnabled
     * @param int    $ppeExpireDays
     * @param bool   $expectsProceed
     */
    #[DataProvider('managedExpiryProvider')]
    public function testOnlyOrdersBuckarooWillExpireAreKeptFromTheCleanupCron(
        string $method,
        int $transferDueDate,
        bool $ppeCronEnabled,
        int $ppeExpireDays,
        bool $expectsProceed
    ): void {
        $instance = $this->getInstance();

        $this->transferConfigMock->method('getDueDate')->willReturn($transferDueDate);
        $this->payPerEmailConfigMock->method('getEnabledCronCancelPPE')->willReturn($ppeCronEnabled);
        $this->payPerEmailConfigMock->method('getExpireDays')->willReturn($ppeExpireDays);

        $this->orderRepositoryMock->method('get')->willReturn($this->orderWithMethod($method));

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return true;
        };

        // Everything below happens as if Magento's cleanup cron were the caller.
        $result = $this->cleanupScope->run(
            fn () => $instance->aroundCancel($this->orderManagementMock(), $proceed, self::ORDER_ID)
        );

        $this->assertSame($expectsProceed, $proceedCalled);
        $this->assertSame($expectsProceed, $result);
    }

    /**
     * An order that cannot be read tells us nothing, so Magento keeps deciding.
     */
    public function testAnUnreadableOrderIsLeftToMagento(): void
    {
        $instance = $this->getInstance();

        $this->orderRepositoryMock->method('get')
            ->willThrowException(new NoSuchEntityException(__('no such order')));

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return false;
        };

        $this->cleanupScope->run(
            fn () => $instance->aroundCancel($this->orderManagementMock(), $proceed, self::ORDER_ID)
        );

        $this->assertTrue($proceedCalled, 'an unreadable order must not be silently protected');
    }

    /**
     * Reporting false rather than true matters: OrderService returns true whenever canCancel() was
     * true, which is how a blocked cancellation ends up looking like a successful one.
     */
    public function testAProtectedOrderReportsThatItWasNotCancelled(): void
    {
        $instance = $this->getInstance();

        $this->transferConfigMock->method('getDueDate')->willReturn(7);
        $this->orderRepositoryMock->method('get')
            ->willReturn($this->orderWithMethod('buckaroo_magento2_transfer'));

        $result = $this->cleanupScope->run(
            fn () => $instance->aroundCancel(
                $this->orderManagementMock(),
                function () {
                    $this->fail('the cancellation must not reach Magento');
                },
                self::ORDER_ID
            )
        );

        $this->assertFalse($result);
    }
}
