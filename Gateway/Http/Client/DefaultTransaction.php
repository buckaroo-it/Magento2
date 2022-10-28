<?php

/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Exception;
use Psr\Log\LoggerInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;

/**/

class DefaultTransaction implements ClientInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Logger
     */
    protected $customLogger;

    /**
     * @var BuckarooAdapter
     */
    protected $adapter;

    protected string $action;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $customLogger,
        BuckarooAdapter $adapter,
        $action = TransactionType::PAY
    ) {
        $this->logger = $logger;
        $this->customLogger = $customLogger;
        $this->adapter = $adapter;
        $this->action = $action;
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();
        $log = [
            'request' => $data,
            'client' => static::class
        ];
        $response['object'] = [];
        $paymentMethod = $data['payment_method'];
        unset($data['payment_method']);

        try {
            $response['object'] = $this->process($paymentMethod, $data);
        } catch (Exception $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->critical($message);
            throw new ClientException($message);
        } finally {
            $log['response'] = (array) $response['object'];
            $this->customLogger->debug($log);
        }

        return $response;
    }

    /**
     * Process http request
     * @param string $paymentMethod
     * @param array $data
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        if (isset($data['encryptedCardData'])) {
            $this->action = TransactionType::PAY_ENCRYPTED;
        }
        return $this->adapter->execute($this->action, $paymentMethod, $data);
    }
}
