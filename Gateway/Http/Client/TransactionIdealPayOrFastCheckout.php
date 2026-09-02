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

class TransactionIdealPayOrFastCheckout extends TransactionPayRemainder
{
    public const FAST_CHECKOUT_ISSUER = 'fastcheckout';

    /**
     * @inheritdoc
     *
     * When the issuer is 'fastcheckout', use the PayFastCheckout action (no Issuer parameter).
     * Otherwise, fall back to the normal pay / payRemainder flow.
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        if (($data['issuer'] ?? '') === self::FAST_CHECKOUT_ISSUER) {
            unset($data['issuer']);
            return $this->adapter->execute(TransactionType::PAY_FAST_CHECKOUT, $paymentMethod, $data);
        }

        return parent::process($paymentMethod, $data);
    }
}
