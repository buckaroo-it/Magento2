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

class TransactionType
{
    public const AUTHORIZE = 'authorize';
    public const AUTHORIZE_ENCRYPTED = 'authorizeEncrypted';
    public const CANCEL = 'cancelAuthorize';
    public const CANCEL_RESERVE = 'cancelReserve';
    public const CAPTURE = 'capture';
    public const PAY = 'pay';
    public const PAY_IN_INSTALLMENTS = 'payInInstallments';
    public const REFUND = 'refund';
    public const RESERVE = 'reserve';
    public const PAY_ENCRYPTED = 'payEncrypted';
    public const PAY_REDIRECT = 'payRedirect';
    public const CREATE_CREDIT_NOTE = 'createCreditNote';
    public const PAYMENT_INVITATION = 'paymentInvitation';
    public const VERIFY = 'verify';
    public const PAY_WITH_TOKEN = 'payWithToken';
    public const AUTHORIZE_WITH_TOKEN = 'authorizeWithToken';

    public const PAY_FAST_CHECKOUT = 'payFastCheckout';

    public const PAY_REMAINDER = 'payRemainder';
    public const PAY_REMAINDER_ENCRYPTED = 'payRemainderEncrypted';
    public const PAY_REMAINDER_WITH_TOKEN = 'payRemainderWithToken';

    public const UPDATE_RESERVE = 'updateReserve';
    public const EXTEND_RESERVE = 'extendReserve';
}
