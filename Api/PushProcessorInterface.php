<?php

namespace Buckaroo\Magento2\Api;

interface PushProcessorInterface
{
    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool;
}
