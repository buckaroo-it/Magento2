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

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

/**
 * Validator for skipped refunds (when amount is 0 after group transaction processing)
 */
class RefundSkippedValidator extends AbstractValidator
{
    /**
     * Validates if refund was skipped (already completed via group transactions)
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        // If refund was fully handled by group transactions (giftcards/vouchers), mark as valid/successful
        if (isset($validationSubject['response']['group_transaction_refund_complete'])
            && $validationSubject['response']['group_transaction_refund_complete'] === true
        ) {
            return $this->createResult(
                true,
                [__('Refund completed via group transactions (giftcards/vouchers)')]
            );
        }

        // Refund not handled by group transactions - continue with normal validation
        return $this->createResult(true);
    }
}
