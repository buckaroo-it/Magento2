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

namespace Buckaroo\Magento2\Gateway\Command;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionPayRemainder;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Buckaroo\Magento2\Model\Service\CancelOrder;
use Buckaroo\Magento2\Service\SpamLimitService;
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
     * @var ErrorMessageMapperInterface
     */
    private $errorMessageMapper;

    /**
     * @var SkipCommandInterface|null
     */
    private ?SkipCommandInterface $skipCommand;

    /**
     * @var SpamLimitService
     */
    private SpamLimitService $spamLimitService;

    /**
     * @var CancelOrder
     */
    private CancelOrder $cancelOrder;

    /**
     * @var LockManagerWrapper
     */
    private LockManagerWrapper $lockManager;

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param SpamLimitService $spamLimitService
     * @param CancelOrder $cancelOrder
     * @param LockManagerWrapper $lockManager
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     * @param SkipCommandInterface|null $skipCommand
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
        ?SkipCommandInterface        $skipCommand = null
    ) {
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
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): void
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $orderIncrementId = $order->getIncrementId();

        // Acquire lock for this order to prevent race conditions with push notifications and return URLs
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementId, 5);
        if (!$lockAcquired) {
            throw new CommandException(__('Could not acquire payment processing lock. Please try again.'));
        }

        try {
            $this->cancelOrder->cancelPreviousPendingOrder($paymentDO);

            if ($this->client instanceof TransactionPayRemainder) {
                $commandSubject['action'] = $this->client->setServiceAction($orderIncrementId);
            }

            if ($this->skipCommand !== null && $this->skipCommand->isSkip($commandSubject)) {
                return;
            }

            // Properly handle exceptions to ensure lock is always released
            try {
                $transferO = $this->transferFactory->create(
                    $this->requestBuilder->build($commandSubject)
                );

                $response = $this->client->placeRequest($transferO);
            } catch (ClientException $e) {
                $this->logger->critical('Buckaroo Client Error: ' . $e->getMessage());
                throw new CommandException(__('Payment processing failed: %1', $e->getMessage()));
            } catch (ConverterException $e) {
                $this->logger->critical('Buckaroo Converter Error: ' . $e->getMessage());
                throw new CommandException(__('Payment data conversion failed: %1', $e->getMessage()));
            } catch (\Exception $e) {
                $this->logger->critical('Unexpected Buckaroo Error: ' . $e->getMessage());
                throw new CommandException(__('Payment processing encountered an unexpected error.'));
            }
            if ($this->validator !== null) {
                $result = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));
                if (!$result->isValid()) {
                    try {
                        $this->spamLimitService->updateRateLimiterCount($paymentDO->getPayment()->getMethodInstance());
                    } catch (LimitReachException $th) {
                        $this->spamLimitService->setMaxAttemptsFlags($paymentDO, $th->getMessage());
                        return;
                    }
                    $this->processErrors($result);
                }
            }

            if ($this->handler) {
                $this->handler->handle(
                    $commandSubject,
                    $response
                );
            }
        } finally {
            // Always release the lock, even if an exception occurs
            $this->lockManager->unlockOrder($orderIncrementId);
        }
    }

    /**
     * Tries to map error messages from validation result and logs processed message.
     * Throws an exception with mapped message or default error.
     *
     * @param ResultInterface $result
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
            $messages[] = (string)$result->getFailsDescription()[0] ?? '';
        }


        $errorMessage = '';
        if (!empty($messages)) {
            foreach ($messages as $message) {
                $errorMessage .= __($message) . PHP_EOL;
            }
            $errorMessage = rtrim($errorMessage);
        } else {
            $errorMessage = 'Transaction has been declined. Please try again later.';
        }

        throw new CommandException(__($errorMessage));
    }
}
