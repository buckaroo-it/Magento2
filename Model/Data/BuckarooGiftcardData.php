<?php

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Model\Giftcard;

class BuckarooGiftcardData implements BuckarooGiftcardDataInterface
{
    private $giftcard;

    /**
     * @return Giftcard
     */
    public function getGiftcardModel(): Giftcard
    {
        return $this->giftcard;
    }

    /**
     * @param Giftcard $giftcard
     *
     * @return BuckarooGiftcardDataInterface
     */
    public function setGiftcardModel(Giftcard $giftcard): BuckarooGiftcardDataInterface
    {
        $this->giftcard = $giftcard;
        return $this;
    }
}
