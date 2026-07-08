<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;

interface PushProcessorInterface
{
    /**
     * Process a push request received from Buckaroo.
     *
     * @param \Buckaroo\Magento2\Api\Data\PushRequestInterface $pushRequest
     *
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool;
}
