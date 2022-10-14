<?php
namespace Buckaroo\Magento2\Gateway\Http\Client;

class TransactionType
{
    const AUTHORIZE = 'authorize';
    const CANCEL = 'cancelAuthorize';
    const CANCEL_RESERVE = 'cancelReserve';
    const CAPTURE = 'capture';
    const PAY = 'pay';
    const PAY_IN_INSTALLMENTS = 'payInInstallments';
    const REFUND = 'refund';
    const RESERVE = 'reserve';
    const PAY_ENCRYPTED = 'payEncrypted';
}