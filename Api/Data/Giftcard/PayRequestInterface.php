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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

interface PayRequestInterface
{
    /**
     * Giftcard number
     *
     * @return string
     */
    public function getCardNumber(): string;

    /**
     * Giftcard pin
     *
     * @return string
     */
    public function getCardPin(): string;

    /**
     * Set giftcard number
     *
     * @param string $cardNumber
     */
    public function setCardNumber(string $cardNumber);

    /**
     * Set giftcard pin
     *
     * @param string $cardPin
     */
    public function setCardPin(string $cardPin);
}
