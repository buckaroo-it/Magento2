<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class TransactionPayRemainder extends AbstractTransaction
{
    private PaymentGroupTransaction $paymentGroupTransaction;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     * @param PaymentGroupTransaction $paymentGroupTransaction
     */
    public function __construct(
        LoggerInterface         $logger,
        Logger                  $customLogger,
        BuckarooAdapter         $adapter,
        PaymentGroupTransaction $paymentGroupTransaction
    ) {
        parent::__construct($logger, $customLogger, $adapter);
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod, array $data)
    {
        $serviceAction = $this->getPayRemainder($data);
        return $this->adapter->$serviceAction($paymentMethod, $data);
    }

    /**
     * If we have already paid some value we do a pay reminder request
     *
     * @param array $data
     * @param string $serviceAction
     * @param string $newServiceAction
     *
     * @return string
     */
    protected function getPayRemainder(&$data, $serviceAction = 'pay', $newServiceAction = 'payRemainder'): string
    {
        $incrementId = $data['invoice'];

        $alreadyPaid = $this->paymentGroupTransaction->getAlreadyPaid($incrementId);

        if ($alreadyPaid > 0) {
            $serviceAction = $newServiceAction;

            $payRemainder = $this->getPayRemainderAmount($data['amountDebit'], $alreadyPaid);
            $data['amountDebit'] = $payRemainder;
            $data['originalTransactionKey'] = $this->paymentGroupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);
        }
        return $serviceAction;
    }

    protected function getPayRemainderAmount($total, $alreadyPaid)
    {
        return $total - $alreadyPaid;
    }
}
