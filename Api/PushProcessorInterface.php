<?php

namespace Buckaroo\Magento2\Api;

interface PushProcessorInterface
{
    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool;

    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processSucceededPush(PushRequestInterface $pushRequest): bool;

    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processFailedPush(PushRequestInterface $pushRequest): bool;

    /**
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPendingPaymentPush(PushRequestInterface $pushRequest): bool;
}