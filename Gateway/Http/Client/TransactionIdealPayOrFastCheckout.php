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
