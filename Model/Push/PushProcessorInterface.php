<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;

interface PushProcessorInterface
{
    /**
     * @param  \Buckaroo\Magento2\Api\Data\PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool;
}
