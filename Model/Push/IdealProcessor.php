<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
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
        parent::processPush($pushRequest);
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        return ($this->pushRequest->hasPostData('statuscode', $statusCodeSuccess)
            && $this->pushRequest->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY));
    }

    public function processSucceded()
    {
        $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        if ($this->pushRequest->hasPostData('statuscode', $statusCodeSuccess)
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