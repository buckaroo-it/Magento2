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

    public const PAY_REMAINDER = 'payRemainder';
    public const PAY_REMAINDER_ENCRYPTED = 'payRemainderEncrypted';

    /**
     * Get Pay Remainder Service Actions
     *
     * @return string[]
     */
    public static function getPayRemainderActions(): array
    {
        return [self::PAY_REMAINDER, self::PAY_REMAINDER_ENCRYPTED];
    }
}
