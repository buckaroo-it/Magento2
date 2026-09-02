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

namespace Buckaroo\Magento2\Model\Giftcard\Request;

use Magento\Quote\Api\Data\CartInterface;

interface GiftcardInterface
{
    /**
     * Send giftcard request
     *
     * @return mixed
     */
    public function send();

    /**
     * Set card number
     *
     * @param string $cardNumber
     *
     * @return GiftcardInterface
     */
    public function setCardNumber(string $cardNumber): GiftcardInterface;

    /**
     * Set card pin
     *
     * @param string $pin
     *
     * @return GiftcardInterface
     */
    public function setPin(string $pin): GiftcardInterface;

    /**
     * Set card type
     *
     * @param string $cardId
     *
     * @return GiftcardInterface
     */
    public function setCardId(string $cardId): GiftcardInterface;

    /**
     * Set quote
     *
     * @param CartInterface $quote
     *
     * @return GiftcardInterface
     */
    public function setQuote(CartInterface $quote): GiftcardInterface;
}
