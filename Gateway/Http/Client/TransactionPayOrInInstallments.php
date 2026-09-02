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

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Transaction\Response\TransactionResponse;

class TransactionPayOrInInstallments extends DefaultTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        $action = TransactionType::PAY_IN_INSTALLMENTS;

        if (($data['additionalParameters']['service_action_from_magento'] ?? null) === TransactionType::PAY) {
            $action = TransactionType::PAY;
        }

        return $this->adapter->execute($action, $paymentMethod, $data);
    }
}
