<?php

namespace Buckaroo\Magento2\Service;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\LockerProcessInterface;

class LockerProcess implements LockerProcessInterface
{
    /**
     * @var File
     */
    private File $fileSystemDriver;

    /**
     * @var DirectoryList
     */
    private DirectoryList $dirList;

    /**
     * @var string
     */
    private $lockFilePath;

    public function __construct(File $fileSystemDriver, DirectoryList $dirList)
    {
        $this->fileSystemDriver = $fileSystemDriver;
        $this->dirList = $dirList;
    }

    /**
     * Lock the process
     *
     * @return resource|void
     * @throws FileSystemException
     */
    public function lockProcess($lockName)
    {
        if ($this->lockFilePath = $this->getLockProcessingFilePath($lockName)) {
            if ($fp = $this->fileSystemDriver->fileOpen($this->lockFilePath, "w+")) {
                $this->fileSystemDriver->fileLock($fp);
                return $fp;
            }
        }
    }

    /**
     * Unlock the process.
     *
     * @throws FileSystemException
     */
    public function unlockProcess(): void
    {
        if ($this->lockFilePath && $this->fileSystemDriver->isExists($this->lockFilePath)) {
            $this->fileSystemDriver->deleteFile($this->lockFilePath);
        }
    }

    /**
     * Get the file path for the lock push processing file.
     *
     * @param string $lockName
     * @return string
     * @throws FileSystemException
     */
    private function getLockProcessingFilePath(string $lockName): string
    {
        return $this->dirList->getPath('tmp') . DIRECTORY_SEPARATOR . 'bk_push_ppe_' . sha1($lockName);
    }

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
}