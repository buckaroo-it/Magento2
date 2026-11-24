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

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Exceptions\BuckarooException as SdkException;
use Buckaroo\Magento2\Exception\UnknownOutcomeException;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\Adapter\TransportFailureClassifier;
use Buckaroo\Magento2\Observer\AddInTestModeMessage;
use Buckaroo\Magento2\Service\Transaction\OutcomeResolver;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ObjectManager;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Default Gateway Client
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DefaultTransaction implements ClientInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Logs payment related information used for debug
     *
     * @var Logger
     */
    protected $paymentLogger;

    /**
     * @var BuckarooAdapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var OutcomeResolver|null
     */
    private ?OutcomeResolver $outcomeResolver;

    /**
     * @var TransportFailureClassifier|null
     */
    private ?TransportFailureClassifier $transportFailureClassifier;

    /**
     * @param LoggerInterface                 $logger
     * @param Logger                          $paymentLogger
     * @param BuckarooAdapter                 $adapter
     * @param string                          $action
     * @param OutcomeResolver|null            $outcomeResolver
     * @param TransportFailureClassifier|null $transportFailureClassifier
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $paymentLogger,
        BuckarooAdapter $adapter,
        string $action = TransactionType::PAY,
        ?OutcomeResolver $outcomeResolver = null,
        ?TransportFailureClassifier $transportFailureClassifier = null
    ) {
        $this->logger = $logger;
        $this->paymentLogger = $paymentLogger;
        $this->adapter = $adapter;
        $this->action = $action;
        $this->outcomeResolver = $outcomeResolver;
        $this->transportFailureClassifier = $transportFailureClassifier;
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $data = $transferObject->getBody();
        $log = [
            'request' => $data,
            'client' => static::class
        ];
        $response['object'] = [];
        $paymentMethod = $data['payment_method'] ?? '';
        unset($data['payment_method']);

        if ($this->action === TransactionType::REFUND &&
            isset($data['amountCredit']) &&
            $data['amountCredit'] < 0.01) {

            $this->logger->debug(
                'Skipping refund API call - amount already fully refunded via group transactions (giftcards/vouchers)'
            );

            $response['group_transaction_refund_complete'] = true;
            return $response;
        }

        $startedAt = time();
        try {
            $response['object'] = $this->process($paymentMethod, $data);
        } catch (SdkException $e) {
            // The SDK message carries HTTP and connection details: log it, show a generic message.
            $failure = $this->getTransportFailureClassifier()->classify($e);
            $this->logger->critical($e->getMessage(), [
                'transport_failure' => $failure->getKind(),
                'connection'        => $failure->getDetails(),
            ]);

            if ($failure->isOutcomeUnknown()) {
                $resolved = $this->resolveLostAnswer($paymentMethod, $data, $startedAt);
                if ($resolved !== null) {
                    $response['object'] = $resolved;
                    return $response;
                }

                throw new UnknownOutcomeException(
                    __(
                        'Unfortunately an error occurred while processing your payment. Please try again. '
                        . 'If this error persists, please choose a different payment method.'
                    ),
                    (string)$this->getRequestAction($data),
                    (string)($data['invoice'] ?? ''),
                    $e
                );
            }

            throw new ClientException(
                __(
                    'Unfortunately an error occurred while processing your payment. Please try again. '
                    . 'If this error persists, please choose a different payment method.'
                ),
                $e
            );
        } catch (\Exception $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->critical($message);
            throw new ClientException($message);
        } finally {
            $log['response'] = (array)$response['object'];
            $this->paymentLogger->debug($log);
        }

        return $response;
    }

    /**
     * Process http request
     *
     * @param string $paymentMethod
     * @param array  $data
     *
     * @throws Throwable
     *
     * @return TransactionResponse
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        return $this->adapter->execute($this->getRequestAction($data), $paymentMethod, $data);
    }

    /**
     * Configured SDK action of this client (pay, refund, capture, ...)
     *
     * @return string
     */
    public function getAction(): string
    {
        return (string)$this->action;
    }

    /**
     * SDK action used for this request
     *
     * @param array $data
     *
     * @return string
     */
    protected function getRequestAction(array $data): string
    {
        // A local variable: the client is shared, so changing $this->action would carry over to later requests.
        return isset($data['encryptedCardData']) ? TransactionType::PAY_ENCRYPTED : $this->action;
    }

    /**
     * The request reached Buckaroo but its answer was lost: find the transaction it created
     *
     * @param string $paymentMethod
     * @param array  $data
     * @param int    $startedAt
     *
     * @return TransactionResponse|null
     */
    private function resolveLostAnswer(string $paymentMethod, array $data, int $startedAt): ?TransactionResponse
    {
        $wasTest = $data[AddInTestModeMessage::PAYMENT_IN_TEST_MODE] ?? null;

        return $this->getOutcomeResolver()->resolve(
            $this->getRequestAction($data),
            $paymentMethod,
            $data,
            $startedAt,
            $wasTest === null ? null : (bool)$wasTest
        );
    }

    /**
     * Subclasses construct this class with the original three arguments
     *
     * @return OutcomeResolver
     */
    private function getOutcomeResolver(): OutcomeResolver
    {
        if ($this->outcomeResolver === null) {
            $this->outcomeResolver = ObjectManager::getInstance()->get(OutcomeResolver::class);
        }

        return $this->outcomeResolver;
    }

    /**
     * Subclasses construct this class with the original three arguments
     *
     * @return TransportFailureClassifier
     */
    private function getTransportFailureClassifier(): TransportFailureClassifier
    {
        if ($this->transportFailureClassifier === null) {
            // Stateless and without dependencies
            $this->transportFailureClassifier = new TransportFailureClassifier();
        }

        return $this->transportFailureClassifier;
    }
}
