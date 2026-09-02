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

namespace Buckaroo\Magento2\Gateway\Response;

use Magento\Sales\Api\Data\OrderPaymentInterface;

class RefundTransactionIdHandler extends TransactionIdHandler
{
    /**
     * Whether a transaction key should be saved on additional information
     *
     * @return bool
     */
    protected function shouldSaveTransactionKey(): bool
    {
        return false;
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction(): bool
    {
        return true;
    }

    /**
     * Whether parent transaction should be closed
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldCloseParentTransaction(OrderPaymentInterface $payment): bool
    {
        return true;
    }
}
