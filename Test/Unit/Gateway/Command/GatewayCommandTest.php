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

use Buckaroo\Magento2\Gateway\Command\GatewayCommand;
use Buckaroo\Magento2\Gateway\Command\SkipCommandInterface;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayRemainder;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Buckaroo\Magento2\Model\Service\CancelOrder;
use Buckaroo\Magento2\Service\SpamLimitService;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderAdapterInterfaceStub;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Behavioral tests for the gateway command executor.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GatewayCommandTest extends BaseTest
{
    private const ORDER_INCREMENT_ID = '100000123';
    private const LOCK_TIMEOUT_SECONDS = 5;
    private const DEFAULT_DECLINE_MESSAGE = 'Transaction has been declined. Please try again later.';

    /**
     * @var BuilderInterface|MockObject
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface|MockObject
     */
    private $transferFactory;

    /**
     * @var ClientInterface|MockObject
     */
    private $client;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var SpamLimitService|MockObject
     */
    private $spamLimitService;

    /**
     * @var CancelOrder|MockObject
     */
    private $cancelOrder;

    /**
     * @var LockManagerWrapper|MockObject
     */
    private $lockManager;

    /**
     * @var HandlerInterface|MockObject
     */
    private $handler;

    /**
     * @var ValidatorInterface|MockObject
     */
    private $validator;

    /**
     * @var ErrorMessageMapperInterface|MockObject
     */
    private $errorMessageMapper;

    /**
     * @var SkipCommandInterface|MockObject
     */
    private $skipCommand;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private $paymentDO;

    /**
     * @var MethodInterface|MockObject
     */
    private $methodInstance;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestBuilder = $this->createMock(BuilderInterface::class);
        $this->transferFactory = $this->createMock(TransferFactoryInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->spamLimitService = $this->createMock(SpamLimitService::class);
        $this->cancelOrder = $this->createMock(CancelOrder::class);
        $this->lockManager = $this->createMock(LockManagerWrapper::class);
        $this->handler = $this->createMock(HandlerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->errorMessageMapper = $this->createMock(ErrorMessageMapperInterface::class);
        $this->skipCommand = $this->createMock(SkipCommandInterface::class);
    }

    public function testExecuteHappyPathFlowsExactPayloadsThroughPipeline(): void
    {
        $commandSubject = $this->createCommandSubject();
        $request = ['payment_method' => 'ideal', 'invoice' => self::ORDER_INCREMENT_ID, 'amountDebit' => 10.0];
        $response = ['object' => 'transaction-response', 'status' => 'success'];
        $transfer = $this->createMock(TransferInterface::class);

        $this->expectLockAcquiredAndReleased();

        $this->cancelOrder->expects($this->once())
            ->method('cancelPreviousPendingOrder')
            ->with($this->identicalTo($this->paymentDO));

        $this->requestBuilder->expects($this->once())
            ->method('build')
            ->with($commandSubject)
            ->willReturn($request);

        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with($request)
            ->willReturn($transfer);

        $this->client->expects($this->once())
            ->method('placeRequest')
            ->with($this->identicalTo($transfer))
            ->willReturn($response);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(array_merge($commandSubject, ['response' => $response]))
            ->willReturn($this->createValidationResult(true));

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($commandSubject, $response);

        $this->errorMessageMapper->expects($this->never())->method('getMessage');
        $this->spamLimitService->expects($this->never())->method('updateRateLimiterCount');

        $this->createCommand()->execute($commandSubject);
    }

    public function testExecuteThrowsWhenLockCannotBeAcquired(): void
    {
        $commandSubject = $this->createCommandSubject();

        $this->lockManager->expects($this->once())
            ->method('lockOrder')
            ->with(self::ORDER_INCREMENT_ID, self::LOCK_TIMEOUT_SECONDS)
            ->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlockOrder');

        $this->cancelOrder->expects($this->never())->method('cancelPreviousPendingOrder');
        $this->requestBuilder->expects($this->never())->method('build');
        $this->client->expects($this->never())->method('placeRequest');

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Could not acquire payment processing lock. Please try again.');

        $this->createCommand()->execute($commandSubject);
    }

    public function testExecuteThrowsInvalidArgumentBeforeLockingWhenPaymentIsMissing(): void
    {
        $this->lockManager->expects($this->never())->method('lockOrder');

        $this->expectException(\InvalidArgumentException::class);

        $this->createCommand()->execute([]);
    }

    public function testExecuteSkipsGatewayCallButStillUnlocksWhenSkipCommandMatches(): void
    {
        $commandSubject = $this->createCommandSubject();

        $this->expectLockAcquiredAndReleased();

        $this->cancelOrder->expects($this->once())
            ->method('cancelPreviousPendingOrder')
            ->with($this->identicalTo($this->paymentDO));

        $this->skipCommand->expects($this->once())
            ->method('isSkip')
            ->with($commandSubject)
            ->willReturn(true);

        $this->requestBuilder->expects($this->never())->method('build');
        $this->transferFactory->expects($this->never())->method('create');
        $this->client->expects($this->never())->method('placeRequest');
        $this->validator->expects($this->never())->method('validate');
        $this->handler->expects($this->never())->method('handle');

        $this->createCommand(null, true, true, true, true)->execute($commandSubject);
    }

    public function testExecuteProceedsWhenSkipCommandReturnsFalse(): void
    {
        $commandSubject = $this->createCommandSubject();

        $this->expectLockAcquiredAndReleased();
        $this->skipCommand->expects($this->once())
            ->method('isSkip')
            ->with($commandSubject)
            ->willReturn(false);

        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'success']);

        $this->client->expects($this->once())->method('placeRequest');

        $this->createCommand(null, false, false, false, true)->execute($commandSubject);
    }

    /**
     * @param \Exception $clientError
     * @param string $expectedCommandMessage
     * @param string $expectedLogMessage
     */
    #[DataProvider('clientErrorProvider')]
    public function testClientErrorsAreWrappedInCommandExceptionAndLogged(
        \Exception $clientError,
        string $expectedCommandMessage,
        string $expectedLogMessage
    ): void {
        $commandSubject = $this->createCommandSubject();
        $request = ['invoice' => self::ORDER_INCREMENT_ID];
        $transfer = $this->createMock(TransferInterface::class);

        $this->expectLockAcquiredAndReleased();
        $this->requestBuilder->method('build')->willReturn($request);
        $this->transferFactory->method('create')->with($request)->willReturn($transfer);

        $this->client->expects($this->once())
            ->method('placeRequest')
            ->with($this->identicalTo($transfer))
            ->willThrowException($clientError);

        $this->logger->expects($this->once())
            ->method('critical')
            ->with($expectedLogMessage);

        $this->validator->expects($this->never())->method('validate');
        $this->handler->expects($this->never())->method('handle');

        try {
            $this->createCommand()->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame($expectedCommandMessage, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{0: \Exception, 1: string, 2: string}>
     */
    public static function clientErrorProvider(): array
    {
        return [
            'client exception exposes reason' => [
                new ClientException(__('Connection timed out')),
                'Payment processing failed: Connection timed out',
                'Buckaroo Client Error: Connection timed out',
            ],
            'converter exception exposes reason' => [
                new ConverterException(__('Malformed response body')),
                'Payment data conversion failed: Malformed response body',
                'Buckaroo Converter Error: Malformed response body',
            ],
            'generic exception hides internal detail' => [
                new \RuntimeException('internal stack detail'),
                'Payment processing encountered an unexpected error.',
                'Unexpected Buckaroo Error: internal stack detail',
            ],
        ];
    }

    public function testGenericRequestBuilderFailureIsWrappedWithoutLeakingDetail(): void
    {
        $commandSubject = $this->createCommandSubject();

        $this->expectLockAcquiredAndReleased();

        $this->requestBuilder->expects($this->once())
            ->method('build')
            ->willThrowException(new \RuntimeException('db credentials leaked'));

        $this->transferFactory->expects($this->never())->method('create');
        $this->client->expects($this->never())->method('placeRequest');

        try {
            $this->createCommand()->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame('Payment processing encountered an unexpected error.', $exception->getMessage());
            $this->assertStringNotContainsString('db credentials leaked', $exception->getMessage());
        }
    }

    public function testValidationFailureWithErrorCodesConsultsMapperAndThrowsMappedMessages(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'failed']);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(array_merge($commandSubject, ['response' => ['status' => 'failed']]))
            ->willReturn($this->createValidationResult(false, [], ['491', 'F103']));

        $this->errorMessageMapper->expects($this->exactly(2))
            ->method('getMessage')
            ->willReturnCallback(function (string $code) {
                $mapping = [
                    '491' => __('Invalid parameter'),
                    'F103' => __('Invalid bank account number'),
                ];
                $this->assertArrayHasKey($code, $mapping, 'Mapper consulted with unexpected code: ' . $code);
                return $mapping[$code];
            });

        $this->spamLimitService->expects($this->once())
            ->method('updateRateLimiterCount')
            ->with($this->identicalTo($this->methodInstance));

        $this->captureCriticalLogs();
        $this->handler->expects($this->never())->method('handle');

        try {
            $this->createCommand()->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame(
                'Invalid parameter' . PHP_EOL . 'Invalid bank account number',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            ['Payment Error: Invalid parameter', 'Payment Error: Invalid bank account number'],
            $this->capturedLogs
        );
    }

    public function testValidationFailureWithUnmappedCodeFallsBackToDefaultMessage(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'failed']);

        $this->validator->method('validate')
            ->willReturn($this->createValidationResult(false, [], ['490']));

        $this->errorMessageMapper->expects($this->once())
            ->method('getMessage')
            ->with('490')
            ->willReturn(null);

        $this->captureCriticalLogs();

        try {
            $this->createCommand()->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame(self::DEFAULT_DECLINE_MESSAGE, $exception->getMessage());
        }

        $this->assertSame(['Payment Error: 490'], $this->capturedLogs);
    }

    public function testValidationFailureWithoutConfiguredMapperUsesDefaultMessageAndLogsRawCode(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'failed']);

        $this->validator->method('validate')
            ->willReturn($this->createValidationResult(false, [], ['123']));

        $this->captureCriticalLogs();

        try {
            $this->createCommand(null, true, true, false)->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame(self::DEFAULT_DECLINE_MESSAGE, $exception->getMessage());
        }

        $this->assertSame(['Payment Error: 123'], $this->capturedLogs);
    }

    public function testValidationFailureWithFailsDescriptionUsesOnlyFirstDescriptionAndSkipsMapper(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'failed']);

        $this->validator->method('validate')
            ->willReturn($this->createValidationResult(
                false,
                [__('Card declined by issuer'), __('Second failure that is ignored')],
                ['491']
            ));

        $this->errorMessageMapper->expects($this->never())->method('getMessage');
        $this->logger->expects($this->never())->method('critical');

        try {
            $this->createCommand()->execute($commandSubject);
            $this->fail('Expected CommandException was not thrown');
        } catch (CommandException $exception) {
            $this->assertSame('Card declined by issuer', $exception->getMessage());
        }
    }

    public function testValidationFailureWhenSpamLimitReachedSetsFlagsInsteadOfThrowing(): void
    {
        $commandSubject = $this->createCommandSubject();
        $response = ['status' => 'failed'];
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, $response);

        $this->validator->method('validate')
            ->willReturn($this->createValidationResult(false, [], ['491']));

        $this->spamLimitService->expects($this->once())
            ->method('updateRateLimiterCount')
            ->with($this->identicalTo($this->methodInstance))
            ->willThrowException(new LimitReachException('Max payment attempts reached'));

        $this->spamLimitService->expects($this->once())
            ->method('setMaxAttemptsFlags')
            ->with($this->identicalTo($this->paymentDO), 'Max payment attempts reached');

        $this->errorMessageMapper->expects($this->never())->method('getMessage');

        // Pins current behavior: the response handler still runs after the
        // spam-limit short-circuit even though validation failed.
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($commandSubject, $response);

        $this->createCommand()->execute($commandSubject);
    }

    public function testExecuteWithoutValidatorAndHandlerCompletesQuietly(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'success']);

        $this->validator->expects($this->never())->method('validate');
        $this->handler->expects($this->never())->method('handle');

        $this->createCommand(null, false, false, false)->execute($commandSubject);
    }

    public function testOrderIsUnlockedEvenWhenValidationThrows(): void
    {
        $commandSubject = $this->createCommandSubject();
        $this->expectLockAcquiredAndReleased();
        $this->stubSuccessfulGatewayRoundTrip($commandSubject, ['status' => 'failed']);

        $this->validator->method('validate')
            ->willReturn($this->createValidationResult(false, [__('Declined')], []));

        $this->expectException(CommandException::class);

        $this->createCommand()->execute($commandSubject);
    }

    public function testPayRemainderClientInjectsServiceActionIntoCommandSubject(): void
    {
        $commandSubject = $this->createCommandSubject();
        $expectedSubjectWithAction = array_merge($commandSubject, ['action' => 'payRemainder']);
        $request = ['invoice' => self::ORDER_INCREMENT_ID];
        $response = ['status' => 'success'];
        $transfer = $this->createMock(TransferInterface::class);

        $payRemainderClient = $this->createMock(TransactionPayRemainder::class);
        $payRemainderClient->expects($this->once())
            ->method('setServiceAction')
            ->with(self::ORDER_INCREMENT_ID)
            ->willReturn('payRemainder');

        $this->expectLockAcquiredAndReleased();

        $this->requestBuilder->expects($this->once())
            ->method('build')
            ->with($expectedSubjectWithAction)
            ->willReturn($request);
        $this->transferFactory->method('create')->with($request)->willReturn($transfer);

        $payRemainderClient->expects($this->once())
            ->method('placeRequest')
            ->with($this->identicalTo($transfer))
            ->willReturn($response);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with(array_merge($expectedSubjectWithAction, ['response' => $response]))
            ->willReturn($this->createValidationResult(true));

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($expectedSubjectWithAction, $response);

        $this->createCommand($payRemainderClient)->execute($commandSubject);
    }

    /**
     * Build the command under test with optional collaborators.
     */
    private function createCommand(
        ?ClientInterface $client = null,
        bool $withHandler = true,
        bool $withValidator = true,
        bool $withErrorMapper = true,
        bool $withSkipCommand = false
    ): GatewayCommand {
        return new GatewayCommand(
            $this->requestBuilder,
            $this->transferFactory,
            $client ?? $this->client,
            $this->logger,
            $this->spamLimitService,
            $this->cancelOrder,
            $this->lockManager,
            $withHandler ? $this->handler : null,
            $withValidator ? $this->validator : null,
            $withErrorMapper ? $this->errorMessageMapper : null,
            $withSkipCommand ? $this->skipCommand : null
        );
    }

    /**
     * Build a command subject wired to an order with a known increment id.
     *
     * @return array{payment: PaymentDataObjectInterface, amount: float}
     */
    private function createCommandSubject(): array
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn(self::ORDER_INCREMENT_ID);

        $orderAdapter = $this->createMock(OrderAdapterInterfaceStub::class);
        $orderAdapter->method('getOrder')->willReturn($order);

        $this->methodInstance = $this->createMock(MethodInterface::class);

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getMethodInstance')->willReturn($this->methodInstance);

        $this->paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $this->paymentDO->method('getOrder')->willReturn($orderAdapter);
        $this->paymentDO->method('getPayment')->willReturn($payment);

        return ['payment' => $this->paymentDO, 'amount' => 10.0];
    }

    /**
     * Expect the order lock to be acquired with the fixed timeout and released exactly once.
     */
    private function expectLockAcquiredAndReleased(): void
    {
        $this->lockManager->expects($this->once())
            ->method('lockOrder')
            ->with(self::ORDER_INCREMENT_ID, self::LOCK_TIMEOUT_SECONDS)
            ->willReturn(true);

        $this->lockManager->expects($this->once())
            ->method('unlockOrder')
            ->with(self::ORDER_INCREMENT_ID)
            ->willReturn(true);
    }

    /**
     * Stub builder -> transfer factory -> client so the command reaches validation.
     *
     * @param array $commandSubject
     * @param array $response
     */
    private function stubSuccessfulGatewayRoundTrip(array $commandSubject, array $response): void
    {
        $request = ['invoice' => self::ORDER_INCREMENT_ID];
        $transfer = $this->createMock(TransferInterface::class);

        $this->requestBuilder->method('build')->willReturn($request);
        $this->transferFactory->method('create')->with($request)->willReturn($transfer);
        $this->client->method('placeRequest')
            ->with($this->identicalTo($transfer))
            ->willReturn($response);
    }

    /**
     * Create a validator result double with pinned values.
     *
     * @param bool $isValid
     * @param array $failsDescription
     * @param array $errorCodes
     *
     * @return ResultInterface|MockObject
     */
    private function createValidationResult(
        bool $isValid,
        array $failsDescription = [],
        array $errorCodes = []
    ): ResultInterface {
        $result = $this->createMock(ResultInterface::class);
        $result->method('isValid')->willReturn($isValid);
        $result->method('getFailsDescription')->willReturn($failsDescription);
        $result->method('getErrorCodes')->willReturn($errorCodes);

        return $result;
    }

    /**
     * Capture logger->critical() messages into $this->capturedLogs for
     * post-exception assertions.
     */
    private function captureCriticalLogs(): void
    {
        $this->capturedLogs = [];
        $this->logger->method('critical')
            ->willReturnCallback(function ($message): void {
                $this->capturedLogs[] = (string)$message;
            });
    }

    /**
     * @var string[]
     */
    private $capturedLogs = [];
}
