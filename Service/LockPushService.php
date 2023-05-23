<?php

namespace Buckaroo\Magento2\Service;

use Magento\Framework\Exception\FileSystemException;

class LockPushService
{
    public const BUCK_PUSH_IDEAL_PAY = 'C021';

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    private function lockPushProcessingCriteria(): bool
    {
        $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        if (!empty($this->pushRequst->getAdditionalInformation('frompayperemail'))
            || (
                ($this->pushRequst->hasPostData('statuscode', $statusCodeSuccess))
                && $this->pushRequst->hasPostData('transaction_method', 'ideal')
                && $this->pushRequst->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY)
            )
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Lock the push processing if criteria are met.
     *
     * @return resource|void
     * @throws FileSystemException
     */
    private function lockPushProcessing()
    {
        if ($this->lockPushProcessingCriteria()) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            if ($path = $this->getLockPushProcessingFilePath()) {
                if ($fp = $this->fileSystemDriver->fileOpen($path, "w+")) {
                    $this->fileSystemDriver->fileLock($fp, LOCK_EX);
                    $this->logging->addDebug(__METHOD__ . '|5|');
                    return $fp;
                }
            }
        }
    }

    /**
     * Get the file path for the lock push processing file.
     *
     * @return string|false
     * @throws FileSystemException
     */
    private function getLockPushProcessingFilePath()
    {
        if ($brqOrderId = $this->getOrderIncrementId()) {
            return $this->dirList->getPath('tmp') . DIRECTORY_SEPARATOR . 'bk_push_ppe_' . sha1($brqOrderId);
        } else {
            return false;
        }
    }

    /**
     * Unlock the push processing.
     *
     * @param resource $lockHandler
     * @return void
     * @throws FileSystemException
     */
    private function unlockPushProcessing($lockHandler)
    {
        if ($this->lockPushProcessingCriteria()) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $this->fileSystemDriver->fileClose($lockHandler);
            if (($path = $this->getLockPushProcessingFilePath()) && $this->fileSystemDriver->isExists($path)) {
                $this->fileSystemDriver->deleteFile($path);
                $this->logging->addDebug(__METHOD__ . '|5|');
            }
        }
    }
}