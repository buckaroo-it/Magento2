<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class AuthorizeTransactionIdHandler extends TransactionIdHandler
{
    /**
     * Whether transaction key should be saved on additional information
     *
     * @return bool
     */
    protected function shouldSaveTransactionKey(): bool
    {
        return true;
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction(): bool
    {
        return false;
    }

    /**
     * Whether parent transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseParentTransaction(): bool
    {
        return false;
    }
}
