<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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
