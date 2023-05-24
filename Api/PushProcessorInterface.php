<?php

namespace Buckaroo\Magento2\Api;

interface PushProcessorInterface
{
    public function processSucceded();
    public function processFailed();

    /**
     * @param PushRequestInterface $pushRequest
     * @return void
     */
    public function processPush(PushRequestInterface $pushRequest): void;
}