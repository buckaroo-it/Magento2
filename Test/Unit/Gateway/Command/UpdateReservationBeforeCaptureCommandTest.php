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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Command;

use Buckaroo\Magento2\Gateway\Command\UpdateReservationBeforeCaptureCommand;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

/**
 * A reservation without unique article numbers is updated once before the capture, and only with
 * lines that carry every unique article number.
 */
class UpdateReservationBeforeCaptureCommandTest extends TestCase
{
    public function testAReservationWithDuplicateSkusIsUpdatedBeforeTheCapture(): void
    {
        $payment = $this->makePayment(false);
        $calls = [];

        $command = $this->makeCommand(true, $calls);
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with(KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS, true);

        $command->execute($this->subjectFor($payment));

        $this->assertSame(['update', 'capture'], $calls);
    }

    public function testAReservationWithUniqueArticleNumbersIsOnlyCaptured(): void
    {
        $calls = [];

        $this->makeCommand(true, $calls)->execute($this->subjectFor($this->makePayment(true)));

        $this->assertSame(['capture'], $calls);
    }

    public function testAnOrderWithoutDuplicateSkusIsOnlyCaptured(): void
    {
        $calls = [];

        $this->makeCommand(false, $calls)->execute($this->subjectFor($this->makePayment(false)));

        $this->assertSame(['capture'], $calls);
    }

    public function testAFailedUpdateStillLetsTheCaptureRun(): void
    {
        $payment = $this->makePayment(false);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $calls = [];

        $command = $this->makeCommand(true, $calls, new \RuntimeException('refused'));
        $command->execute($this->subjectFor($payment));

        $this->assertSame(['update', 'capture'], $calls);
    }

    public function testAnUpdateWithoutEveryUniqueNumberIsNotSent(): void
    {
        $payment = $this->makePayment(false);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $calls = [];

        $command = $this->makeCommand(true, $calls, null, ['SH00088558-6146', 'SH00088558-6147']);
        $command->execute($this->subjectFor($payment));

        $this->assertSame(['capture'], $calls);
    }

    /**
     * @param bool            $hasDuplicateSku
     * @param array           $calls
     * @param \Exception|null $updateFailure
     * @param string[]        $missingNumbers
     *
     * @return UpdateReservationBeforeCaptureCommand
     */
    private function makeCommand(
        bool $hasDuplicateSku,
        array &$calls,
        ?\Exception $updateFailure = null,
        array $missingNumbers = []
    ) {
        $capture = $this->createMock(CommandInterface::class);
        $capture->method('execute')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'capture';
            return null;
        });

        $update = $this->createMock(CommandInterface::class);
        $update->method('execute')->willReturnCallback(function () use (&$calls, $updateFailure) {
            $calls[] = 'update';
            if ($updateFailure !== null) {
                throw $updateFailure;
            }
            return null;
        });

        $handler = $this->createMock(KlarnaKpHandler::class);
        $handler->method('hasDuplicateSku')->willReturn($hasDuplicateSku);
        $handler->method('getOrderArticlesData')->willReturn(['articles' => []]);
        $handler->method('getMissingUniqueArticleNumbers')->willReturn($missingNumbers);
        $factory = $this->createMock(ArticlesHandlerFactory::class);
        $factory->method('create')->willReturn($handler);

        return new UpdateReservationBeforeCaptureCommand(
            $capture,
            $update,
            $factory,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    /**
     * @param bool $uniqueArticleNumbers
     *
     * @return Payment|\PHPUnit\Framework\MockObject\MockObject
     */
    private function makePayment(bool $uniqueArticleNumbers)
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('300000076');

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn($key = null) => $key === KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS ? $uniqueArticleNumbers : null
        );

        return $payment;
    }

    /**
     * @param Payment $payment
     *
     * @return array
     */
    private function subjectFor($payment): array
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return ['payment' => $paymentDO];
    }
}
