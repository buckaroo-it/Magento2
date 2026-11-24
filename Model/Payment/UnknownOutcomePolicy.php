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

namespace Buckaroo\Magento2\Model\Payment;

use Buckaroo\Magento2\Exception\UnknownOutcomeException;

/**
 * Decides when a checkout keeps its order pending instead of failing because Buckaroo's answer was lost.
 *
 * Only for methods that can take the money in the request itself (wallets, cards entered in the
 * checkout, direct debit, BNPL authorizations, POS): there the shopper may already have paid. Redirect
 * methods move no money until the shopper completes the redirect, which never happened, so failing
 * the checkout as before is right for them.
 */
class UnknownOutcomePolicy
{
    /** Methods that can charge or authorize without a redirect */
    private const DIRECT_METHODS = [
        'buckaroo_magento2_applepay',
        'buckaroo_magento2_googlepay',
        'buckaroo_magento2_creditcards',
        'buckaroo_magento2_clicktopay',
        'buckaroo_magento2_pospayment',
        'buckaroo_magento2_sepadirectdebit',
        'buckaroo_magento2_afterpay',
        'buckaroo_magento2_afterpay2',
        'buckaroo_magento2_afterpay20',
        'buckaroo_magento2_billink',
        'buckaroo_magento2_payperemail',
        'buckaroo_magento2_voucher',
    ];

    /** SDK actions that start a payment (as opposed to refund, capture, cancel) */
    private const PAYMENT_ACTIONS = [
        'pay', 'payEncrypted', 'payWithToken', 'payInInstallments', 'payFastCheckout',
        'payRemainder', 'payRemainderEncrypted', 'payRemainderWithToken',
        'authorize', 'authorizeEncrypted', 'authorizeWithToken', 'reserve', 'paymentInvitation',
    ];

    /**
     * The UnknownOutcomeException behind $throwable, when the order should stay pending for it
     *
     * @param \Throwable $throwable
     * @param string     $methodCode
     *
     * @return UnknownOutcomeException|null
     */
    public function getPendingCause(\Throwable $throwable, string $methodCode): ?UnknownOutcomeException
    {
        if (!in_array($methodCode, self::DIRECT_METHODS, true)) {
            return null;
        }

        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof UnknownOutcomeException) {
                return in_array($cause->getAction(), self::PAYMENT_ACTIONS, true) ? $cause : null;
            }
        }

        return null;
    }
}
