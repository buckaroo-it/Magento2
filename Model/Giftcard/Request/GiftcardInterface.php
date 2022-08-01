<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setCardNumber(string $cardNumber);

    /**
     * Set card pin
     *
     * @param string $pin
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setPin(string $pin);

    /**
     * Set card type
     *
     * @param string $cardId
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setCardId(string $cardId);

    /**
     * Set quote
     *
     * @param CartInterface $quote
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setQuote(CartInterface $quote);
}
