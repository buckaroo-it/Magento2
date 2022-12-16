<?php

namespace Buckaroo\Magento2\Plugin\CatalogInventory\Model;

use Buckaroo\Magento2\Model\Session as BuckarooSession;

class Configuration
{
    protected $buckarooSession;

    public function __construct(
        BuckarooSession $buckarooSession
    ) {
        $this->buckarooSession = $buckarooSession;
    }

    /**
     * @param $subject
     * @param $result
     * @return false|mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCanSubtractQty($subject, $result)
    {
        $flag = $this->buckarooSession->getData('flagHandleFailedQuote');
        if ($flag) {
            return false;
        } else {
            return $result;
        }
    }
}
