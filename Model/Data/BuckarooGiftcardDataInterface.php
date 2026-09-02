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

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Model\Giftcard;

interface BuckarooGiftcardDataInterface
{
    /**
     * Get the giftcard model.
     *
     * @return Giftcard
     */
    public function getGiftcardModel(): Giftcard;

    /**
     * Set the giftcard model.
     *
     * @param Giftcard $giftcard
     *
     * @return BuckarooGiftcardDataInterface
     */
    public function setGiftcardModel(Giftcard $giftcard): BuckarooGiftcardDataInterface;
}
