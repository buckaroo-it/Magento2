<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Service\LockerProcess;
use Magento\Framework\Exception\FileSystemException;

class PayPerEmailProcessor extends DefaultProcessor
{
    /**
     * @var LockerProcess
     */
    protected LockerProcess $lockerProcess;


    public function processSucceded()
    {

    }

    public function processFailed()
    {
        // TODO: Implement processFailed() method.
    }


    /**
     * @throws FileSystemException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        if ($this->lockPushProcessingCriteria()) {
            $this->lockerProcess->lockProcess($this->getOrderIncrementId());
        }

        return true;
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        return !empty($this->pushRequest->getAdditionalInformation('frompayperemail'));
    }
}