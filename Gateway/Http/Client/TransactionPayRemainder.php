<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\DefaultTransaction;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;

class TransactionPayRemainder extends DefaultTransaction
{
    private PayReminderService $payReminderService;
    private string $serviceAction;
    private string $newServiceAction;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     * @param PayReminderService $payReminderService
     * @param string $serviceAction
     * @param string $newServiceAction
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $customLogger,
        BuckarooAdapter $adapter,
        PayReminderService $payReminderService,
        string $serviceAction = TransactionType::PAY,
        string $newServiceAction = TransactionType::PAY_REMAINDER
    ) {
        parent::__construct($logger, $customLogger, $adapter);
        $this->payReminderService = $payReminderService;
        $this->serviceAction = $serviceAction;
        $this->newServiceAction = $newServiceAction;
    }

    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        $orderIncrementId = $data['invoice'] ?? $data['order'] ?? '';

        $serviceAction = $this->payReminderService->getServiceAction(
            $orderIncrementId,
            $this->serviceAction,
            $this->newServiceAction
        );

        return $this->adapter->execute($serviceAction, $paymentMethod, $data);
    }
}
