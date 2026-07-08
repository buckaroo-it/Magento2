<?php

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Model\Giftcard;

class BuckarooGiftcardData implements BuckarooGiftcardDataInterface
{
    /**
     * @var Giftcard
     */
    private $giftcard;

    /**
     * Get the giftcard model instance
     *
     * @return Giftcard
     */
    public function getGiftcardModel(): Giftcard
    {
        return $this->giftcard;
    }

    /**
     * Set the giftcard model instance
     *
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
