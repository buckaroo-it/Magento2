<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Magento\Framework\Exception\FileSystemException;

class IdealProcessor extends DefaultProcessor
{
    public const BUCK_PUSH_IDEAL_PAY = 'C021';

    /**
     * @throws FileSystemException
     * @throws BuckarooException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        // Lock Processing
        if ($this->lockPushProcessingCriteria()) {
            $this->lockerProcess->lockProcess($this->getOrderIncrementId());
        }

        parent::processPush($pushRequest);

        $this->lockerProcess->unlockProcess();
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        return $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            && $this->pushRequest->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY);
    }

    public function processSucceded()
    {
        $statusCodeSuccess = BuckarooStatusCode::SUCCESS;

        if ($this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            && $this->pushRequest->hasPostData('transaction_method', 'ideal')
            && $this->pushRequest->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY)
        ) {
            return true;
        }
    }

    public function processFailed()
    {
        // TODO: Implement processFailed() method.
    }


}