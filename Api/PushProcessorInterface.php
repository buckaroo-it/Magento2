<?php

namespace Buckaroo\Magento2\Api;

interface PushProcessorInterface
{
    public function processSucceded();
    public function processFailed();

    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool;
}