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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;

class PayRequest implements PayRequestInterface
{
    /**
     * @var string
     */
    protected $cardNumber;

    /**
     * @var string
     */
    protected $cardPin;

    /**
     * Giftcard number
     *
     * @return string
     */
    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    /**
     * Set giftcard number
     *
     * @param string $cardNumber
     */
    public function setCardNumber(string $cardNumber)
    {
        $this->cardNumber = $cardNumber;
    }

    /**
     * Giftcard pin
     *
     * @return string
     */
    public function getCardPin(): string
    {
        return $this->cardPin;
    }

    /**
     * Giftcard pin
     *
     * @param string $cardPin
     */
    public function setCardPin(string $cardPin)
    {
        $this->cardPin = $cardPin;
    }
}
