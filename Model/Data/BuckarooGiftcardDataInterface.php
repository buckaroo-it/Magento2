<?php

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Model\Giftcard;

interface BuckarooGiftcardDataInterface
{
    /**
     * @return Giftcard
     */
    public function getGiftcardModel(): Giftcard;

    /**
     * @param Giftcard $giftcard
     * @return BuckarooGiftcardDataInterface
     */
    public function setGiftcardModel(Giftcard $giftcard): BuckarooGiftcardDataInterface;
}
