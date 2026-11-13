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

namespace Buckaroo\Magento2\Model\PartialPayment\Instrument;

use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;

/**
 * Something a shopper applies to the cart to pay part of it: a voucher code or a giftcard.
 *
 * The apply service does everything the instruments share (cart checks, amount and currency,
 * sending, the group transaction). An instrument only adds what is its own.
 */
interface InstrumentInterface
{
    /**
     * Payment method code whose configuration governs this instrument
     *
     * @return string
     */
    public function getMethodCode(): string;

    /**
     * Service code the SDK sends the request to
     *
     * @return string
     */
    public function getServiceCode(): string;

    /**
     * Reject the instrument before anything is sent, e.g. when a required field is empty
     *
     * @param Quote $quote
     *
     * @throws PartialPaymentRejectedException
     *
     * @return void
     */
    public function validate(Quote $quote): void;

    /**
     * Request fields of this instrument, added to the shared request body
     *
     * @param Quote $quote
     *
     * @return array
     */
    public function getRequestFields(Quote $quote): array;

    /**
     * Message shown when the gateway rejects the instrument without saying why
     *
     * @return Phrase
     */
    public function getFallbackRejectionMessage(): Phrase;

    /**
     * Turn the gateway's rejection message into the text shown to the shopper
     *
     * @param string $message
     *
     * @return string
     */
    public function formatRejectionMessage(string $message): string;
}
