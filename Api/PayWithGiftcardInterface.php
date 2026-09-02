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

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;

interface PayWithGiftcardInterface
{
    /**
     * Rest method for paying with giftcards
     *
     * @param string              $cartId
     * @param string              $giftcardId
     * @param PayRequestInterface $payment
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface
     */
    public function pay(string $cartId, string $giftcardId, PayRequestInterface $payment);
}
