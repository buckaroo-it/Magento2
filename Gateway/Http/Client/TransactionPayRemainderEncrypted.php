<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\DefaultTransaction;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;

class TransactionPayRemainderEncrypted extends DefaultTransaction
{
    private PayReminderService $payReminderService;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        LoggerInterface         $logger,
        Logger                  $customLogger,
        BuckarooAdapter         $adapter,
        PayReminderService $payReminderService
    ) {
        parent::__construct($logger, $customLogger, $adapter);
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     * @throws \Throwable
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        $orderIncrementId = $data['invoice'] ?? $data['order'] ?? '';
        $serviceAction = $this->payReminderService->getServiceAction($orderIncrementId, 'payEncrypted', 'payRemainderEncrypted');
        return $this->adapter->execute($serviceAction, $paymentMethod, $data);
    }
}