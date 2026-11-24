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

namespace Buckaroo\Magento2\Gateway\Command;

use Buckaroo\Magento2\Gateway\Helper\GatewayFailureDescription;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Exception\UnknownOutcomeException;
use Buckaroo\Magento2\Gateway\Http\Client\DefaultTransaction;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayRemainder;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Payment\UnknownOutcomePolicy;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Buckaroo\Magento2\Model\Service\CancelOrder;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Buckaroo\Magento2\Service\SpamLimitService;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GatewayCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UnknownOutcomePolicy
     */
    private UnknownOutcomePolicy $unknownOutcomePolicy;

    /**
     * @var MessageManagerInterface|null
     */
    private ?MessageManagerInterface $messageManager;

    /**
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache = null;

    /**
     * @var string
     */
    private string $guardedOperation;

    /** Operations that are not sent twice when Buckaroo's answer to the first one was lost */
    private const GUARDED_ACTIONS = [TransactionType::REFUND, TransactionType::CAPTURE];

    /** Seconds the same operation stays blocked after its answer was lost */
    private const UNKNOWN_OUTCOME_BLOCK_SECONDS = 900;

    /**
     * @var ErrorMessageMapperInterface
     */
    private $errorMessageMapper;

    /**
     * @var SkipCommandInterface|null
     */
    private $skipCommand;

    /**
     * @var SpamLimitService
     */
    private $spamLimitService;

    /**
     * @var CancelOrder
     */
    private $cancelOrder;

    /**
     * @var LockManagerWrapper
     */
    private $lockManager;

    /**
     * @param BuilderInterface                 $requestBuilder
     * @param TransferFactoryInterface         $transferFactory
     * @param ClientInterface                  $client
     * @param LoggerInterface                  $logger
     * @param SpamLimitService                 $spamLimitService
     * @param CancelOrder                      $cancelOrder
     * @param LockManagerWrapper               $lockManager
     * @param HandlerInterface|null            $handler
     * @param ValidatorInterface|null          $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     * @param SkipCommandInterface|null        $skipCommand
     * @param UnknownOutcomePolicy|null        $unknownOutcomePolicy
     * @param MessageManagerInterface|null     $messageManager
     * @param CacheInterface|null              $cache
     * @param string                           $guardedOperation refund or capture, when the client's action does not say it
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        BuilderInterface            $requestBuilder,
        TransferFactoryInterface    $transferFactory,
        ClientInterface             $client,
        LoggerInterface             $logger,
        SpamLimitService            $spamLimitService,
        CancelOrder                 $cancelOrder,
        LockManagerWrapper          $lockManager,
        ?HandlerInterface            $handler = null,
        ?ValidatorInterface          $validator = null,
        ?ErrorMessageMapperInterface $errorMessageMapper = null,
        ?SkipCommandInterface        $skipCommand = null,
        ?UnknownOutcomePolicy        $unknownOutcomePolicy = null,
        ?MessageManagerInterface     $messageManager = null,
        ?CacheInterface              $cache = null,
        string                       $guardedOperation = ''
    ) {
        $this->cache = $cache;
        $this->guardedOperation = $guardedOperation;
        $this->unknownOutcomePolicy = $unknownOutcomePolicy ?? new UnknownOutcomePolicy();
        $this->messageManager = $messageManager;
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->errorMessageMapper = $errorMessageMapper;
        $this->skipCommand = $skipCommand;
        $this->spamLimitService = $spamLimitService;
        $this->cancelOrder = $cancelOrder;
        $this->lockManager = $lockManager;
    }

    /**
     * Executes command basing on business object
     *
     * @param array $commandSubject
     *
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException|LocalizedException
     */
    public function execute(array $commandSubject): void
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $orderIncrementId = $order->getIncrementId();

        $this->acquireLock($orderIncrementId);

        try {
            $this->cancelOrder->cancelPreviousPendingOrder($paymentDO);

            if ($this->client instanceof TransactionPayRemainder) {
                $commandSubject['action'] = $this->client->setServiceAction($orderIncrementId);
            }

            if ($this->shouldSkipCommand($commandSubject)) {
                return;
            }

            $this->refuseWhilePreviousOutcomeUnknown($order);

            $marked = $this->lockManager->markGatewayRequestStarted($orderIncrementId);
            try {
                $response = $this->executePaymentRequest($commandSubject);
            } catch (CommandException $e) {
                if ($this->keepPendingOnUnknownOutcome($e, $paymentDO, $order)) {
                    return;
                }
                $this->rememberUnknownOutcome($e, $order);
                throw $e;
            } finally {
                if ($marked) {
                    $this->lockManager->markGatewayRequestFinished($orderIncrementId);
                }
            }

            $this->validateAndHandleResponse($commandSubject, $response, $paymentDO);
        } finally {
            $this->lockManager->unlockOrder($orderIncrementId);
        }
    }

    /**
     * The payment request reached Buckaroo but its answer was lost: keep the order pending
     *
     * For methods that take the money in the request itself the shopper may have paid. Failing the
     * checkout would cancel the order and invite a second payment; instead the order is placed pending,
     * flagged, and settled by Buckaroo's push or the reconciliation cron.
     *
     * @param CommandException           $exception
     * @param PaymentDataObjectInterface $paymentDO
     * @param Order                      $order
     *
     * @return bool whether the order stays pending
     */
    private function keepPendingOnUnknownOutcome(
        CommandException $exception,
        PaymentDataObjectInterface $paymentDO,
        $order
    ): bool {
        $payment = $paymentDO->getPayment();
        $cause = $this->unknownOutcomePolicy->getPendingCause(
            $exception,
            (string)$payment->getMethodInstance()->getCode()
        );

        if ($cause === null) {
            return false;
        }

        $payment->setAdditionalInformation(CancelGuard::OUTCOME_UNKNOWN, time());
        $order->addCommentToStatusHistory(__(
            'Buckaroo did not answer in time. The payment may have been processed; the order waits for'
            . ' Buckaroo\'s confirmation.'
        ));

        $this->getMessageManager()->addNoticeMessage(__(
            'Your payment is being processed. You will receive an email as soon as it is confirmed.'
        ));

        $this->logger->warning(sprintf(
            'Buckaroo outcome unknown for order %s (%s): order kept pending',
            $order->getIncrementId(),
            $cause->getAction()
        ));

        return true;
    }

    /**
     * Report a refund or capture whose answer was lost, and remember it
     *
     * It may have been carried out. Remember it so the same operation is not sent again before it was
     * checked: Buckaroo accepts it twice.
     *
     * @param CommandException $exception
     * @param Order|mixed      $order
     *
     * @throws CommandException
     */
    private function rememberUnknownOutcome(CommandException $exception, $order): void
    {
        $action = $this->getGuardedOperation();
        if ($action === '' || !$this->hasUnknownOutcome($exception)) {
            return;
        }

        $this->getCache()->save(
            (string)time(),
            $this->getUnknownOutcomeCacheKey($order),
            [],
            self::UNKNOWN_OUTCOME_BLOCK_SECONDS
        );

        $this->logger->warning(sprintf(
            'Buckaroo outcome unknown for %s on order %s: same operation blocked for %d seconds',
            $action,
            $order->getIncrementId(),
            self::UNKNOWN_OUTCOME_BLOCK_SECONDS
        ));

        throw new CommandException(__(
            'Buckaroo did not answer in time, so it is not known whether the %1 was carried out. Check the'
            . ' transactions of this order in Buckaroo Plaza before trying again.',
            $action === TransactionType::CAPTURE ? __('capture') : __('refund')
        ), $exception);
    }

    /**
     * Refuse while previous outcome unknown
     *
     * @param Order|mixed $order
     *
     * @throws CommandException
     */
    private function refuseWhilePreviousOutcomeUnknown($order): void
    {
        if ($this->getGuardedOperation() === '' || !$order->getId()) {
            return;
        }

        $since = $this->getCache()->load($this->getUnknownOutcomeCacheKey($order));
        if ($since === false || $since === null || $since === '') {
            return;
        }

        throw new CommandException(__(
            'An earlier refund or capture of this order may still have been carried out by Buckaroo (no answer'
            . ' was received at %1). Check the transactions in Buckaroo Plaza; you can try again after %2.',
            $this->formatLocalTime((int)$since),
            $this->formatLocalTime((int)$since + self::UNKNOWN_OUTCOME_BLOCK_SECONDS)
        ));
    }

    /**
     * Has unknown outcome
     *
     * @param \Throwable $throwable
     *
     * @return bool
     */
    private function hasUnknownOutcome(\Throwable $throwable): bool
    {
        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof UnknownOutcomeException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refund or capture when this command must not be sent twice after a lost answer, '' otherwise
     *
     * @return string
     */
    private function getGuardedOperation(): string
    {
        $action = $this->getClientAction();
        if (in_array($action, self::GUARDED_ACTIONS, true)) {
            return $action;
        }

        return $this->guardedOperation;
    }

    /**
     * Time of day in the store's timezone, as the admin's clock shows it
     *
     * @param int $timestamp
     *
     * @return string
     */
    private function formatLocalTime(int $timestamp): string
    {
        try {
            return ObjectManager::getInstance()->get(TimezoneInterface::class)->date($timestamp)->format('H:i');
        } catch (\Throwable $e) {
            return gmdate('H:i', $timestamp) . ' UTC';
        }
    }

    /**
     * Get client action
     *
     * @return string
     */
    private function getClientAction(): string
    {
        return $this->client instanceof DefaultTransaction ? $this->client->getAction() : '';
    }

    /**
     * Get unknown outcome cache key
     *
     * @param Order|mixed $order
     *
     * @return string
     */
    private function getUnknownOutcomeCacheKey($order): string
    {
        return 'buckaroo_unknown_outcome_' . $this->getGuardedOperation() . '_' . $order->getId();
    }

    /**
     * Get cache
     *
     * @return CacheInterface
     */
    private function getCache(): CacheInterface
    {
        return $this->cache ??= ObjectManager::getInstance()->get(CacheInterface::class);
    }

    /**
     * Get message manager
     *
     * @return MessageManagerInterface
     */
    private function getMessageManager(): MessageManagerInterface
    {
        return $this->messageManager ??= ObjectManager::getInstance()->get(MessageManagerInterface::class);
    }

    /**
     * Acquire lock for order
     *
     * @param string $orderIncrementId
     *
     * @throws CommandException
     */
    private function acquireLock(string $orderIncrementId): void
    {
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementId, 5);
        if (!$lockAcquired) {
            throw new CommandException(__('Could not acquire payment processing lock. Please try again.'));
        }
    }

    /**
     * Check if command should be skipped
     *
     * @param array $commandSubject
     *
     * @return bool
     */
    private function shouldSkipCommand(array $commandSubject): bool
    {
        return $this->skipCommand !== null && $this->skipCommand->isSkip($commandSubject);
    }

    /**
     * Execute payment request
     *
     * @param array $commandSubject
     *
     * @return array
     *
     * @throws CommandException
     */
    private function executePaymentRequest(array $commandSubject): array
    {
        try {
            $transferO = $this->transferFactory->create(
                $this->requestBuilder->build($commandSubject)
            );

            return $this->client->placeRequest($transferO);
        } catch (ClientException $e) {
            $this->logger->critical('Buckaroo Client Error: ' . $e->getMessage());
            throw new CommandException(__('Payment processing failed: %1', $e->getMessage()), $e);
        } catch (ConverterException $e) {
            $this->logger->critical('Buckaroo Converter Error: ' . $e->getMessage());
            throw new CommandException(__('Payment data conversion failed: %1', $e->getMessage()), $e);
        } catch (\Exception $e) {
            $this->logger->critical('Unexpected Buckaroo Error: ' . $e->getMessage());
            throw new CommandException(__('Payment processing encountered an unexpected error.'), $e);
        }
    }

    /**
     * Validate and handle response
     *
     * @param array $commandSubject
     * @param array $response
     * @param PaymentDataObjectInterface $paymentDO
     *
     * @throws CommandException
     */
    private function validateAndHandleResponse(
        array $commandSubject,
        array $response,
        PaymentDataObjectInterface $paymentDO
    ): void {
        if ($this->validator !== null) {
            $result = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));
            if (!$result->isValid()) {
                $this->handleInvalidResult($result, $paymentDO);
            }
        }

        if ($this->handler) {
            $this->handler->handle($commandSubject, $response);
        }
    }

    /**
     * Handle invalid validation result
     *
     * @param ResultInterface $result
     * @param PaymentDataObjectInterface $paymentDO
     *
     * @throws CommandException
     */
    private function handleInvalidResult(ResultInterface $result, PaymentDataObjectInterface $paymentDO): void
    {
        try {
            $this->spamLimitService->updateRateLimiterCount($paymentDO->getPayment()->getMethodInstance());
        } catch (LimitReachException $th) {
            $this->spamLimitService->setMaxAttemptsFlags($paymentDO, $th->getMessage());
            return;
        }
        $this->processErrors($result);
    }

    /**
     * Tries to map error messages from validation result and logs processed message.
     *
     * Throws an exception with mapped message or default error.
     *
     * @param ResultInterface $result
     *
     * @throws CommandException
     */
    private function processErrors(ResultInterface $result)
    {
        $messages = [];
        if (empty($result->getFailsDescription())) {
            $errorsSource = array_merge($result->getErrorCodes(), $result->getFailsDescription());
            foreach ($errorsSource as $errorCodeOrMessage) {
                $errorCodeOrMessage = (string)$errorCodeOrMessage;

                // error messages mapper can be not configured if payment method doesn't have custom error messages.
                if ($this->errorMessageMapper !== null) {
                    $mapped = (string)$this->errorMessageMapper->getMessage($errorCodeOrMessage);
                    if (!empty($mapped)) {
                        $messages[] = $mapped;
                        $errorCodeOrMessage = $mapped;
                    }
                }
                $this->logger->critical('Payment Error: ' . $errorCodeOrMessage);
            }
        } else {
            $failsDescription = (string)($result->getFailsDescription()[0] ?? '');
            $messages[] = $failsDescription;

            if (GatewayFailureDescription::isUninformative($failsDescription)) {
                $this->logger->critical(sprintf(
                    'Payment Error: gateway returned a failure description without a reason. '
                    . 'description: "%s", error_codes: %s',
                    $failsDescription,
                    implode(', ', array_map('strval', $result->getErrorCodes()))
                ));
            }
        }

        // A description that carries no reason must not reach the shopper: keeping it would
        // leave the fallback below unused and show text such as
        // "An error occurred while processing the transaction: ." at checkout.
        $messages = array_values(array_filter($messages, static function ($message) {
            return !GatewayFailureDescription::isUninformative((string)$message);
        }));

        $errorMessage = '';
        if (!empty($messages)) {
            foreach ($messages as $message) {
                $errorMessage .= __($message) . PHP_EOL;
            }
            $errorMessage = rtrim($errorMessage);
        } else {
            $errorMessage = GatewayFailureDescription::STANDARD_DECLINE_MESSAGE;
        }

        throw new CommandException(__($errorMessage));
    }
}
