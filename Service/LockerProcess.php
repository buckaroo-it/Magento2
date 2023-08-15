<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

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
    private string $lockFilePath;

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
}