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
