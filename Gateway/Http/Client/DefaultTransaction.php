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
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Default Gateway Client
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
     * @param LoggerInterface $logger
     * @param Logger          $paymentLogger
     * @param BuckarooAdapter $adapter
     * @param string          $action
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $paymentLogger,
        BuckarooAdapter $adapter,
        string $action = TransactionType::PAY
    ) {
        $this->logger = $logger;
        $this->paymentLogger = $paymentLogger;
        $this->adapter = $adapter;
        $this->action = $action;
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

        try {
            $response['object'] = $this->process($paymentMethod, $data);
        } catch (SdkException $e) {
            // The SDK message carries HTTP and connection details: log it, show a generic message.
            $this->logger->critical($e->getMessage());
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
        // A local variable: the client is shared, so changing $this->action would carry over to later requests.
        $action = isset($data['encryptedCardData']) ? TransactionType::PAY_ENCRYPTED : $this->action;

        return $this->adapter->execute($action, $paymentMethod, $data);
    }
}
