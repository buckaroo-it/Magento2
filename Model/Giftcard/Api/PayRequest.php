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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;

class PayRequest implements PayRequestInterface
{
    /**
     * @var string
     */
    protected string $cardNumber;

    /**
     * @var string
     */
    protected string $cardPin;

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
     * @return void
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
     * @return void
     */
    public function setCardPin(string $cardPin)
    {
        $this->cardPin = $cardPin;
    }
}
