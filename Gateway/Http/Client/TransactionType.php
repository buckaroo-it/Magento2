<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

class TransactionType
{
    public const AUTHORIZE = 'authorize';
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
    public const CREATE_MANDATE = 'createMandate';
    public const PAYMENT_INVITATION = 'paymentInvitation';

}
