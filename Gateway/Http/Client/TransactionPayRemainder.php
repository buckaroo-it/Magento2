<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\PayReminderService;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class TransactionPayRemainder extends AbstractTransaction
{
    private PayReminderService $payReminderService;
    protected OrderFactory $orderFactory;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     * @param PayReminderService $payReminderService
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        LoggerInterface         $logger,
        Logger                  $customLogger,
        BuckarooAdapter         $adapter,
        PayReminderService $payReminderService,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($logger, $customLogger, $adapter);
        $this->payReminderService = $payReminderService;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod, array $data)
    {
        $orderIncrementId = $data['invoice'] ?? $data['order'] ?? '';
        $serviceAction = $this->payReminderService->getServiceAction($orderIncrementId);
        return $this->adapter->$serviceAction($paymentMethod, $data);
    }
}
