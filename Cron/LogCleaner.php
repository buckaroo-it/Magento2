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

namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\ResourceConnection;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ResourceModel\Log as LogResourceModel;
use Buckaroo\Magento2\Model\Config\Source\LogHandler;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Exception\FileSystemException;

class LogCleaner
{
    /**
     * @var LogResourceModel
     */
    private $resource;

    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Log
     */
    private $logging;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var File
     */
    private $driverFile;

    /**
     * @var IoFile
     */
    private $ioFile;

    /**
     * Log Cleaner constructor
     *
     * @param LogResourceModel $resource
     * @param Account $accountConfig
     * @param ResourceConnection $resourceConnection
     * @param Log $logging
     * @param DirectoryList $directoryList
     * @param File $driverFile
     * @param IoFile $ioFile
     */
    public function __construct(
        LogResourceModel $resource,
        Account $accountConfig,
        ResourceConnection $resourceConnection,
        Log $logging,
        DirectoryList $directoryList,
        File $driverFile,
        IoFile $ioFile
    ) {
        $this->resource            = $resource;
        $this->accountConfig       = $accountConfig;
        $this->resourceConnection  = $resourceConnection;
        $this->logging             = $logging;
        $this->directoryList       = $directoryList;
        $this->driverFile          = $driverFile;
        $this->ioFile              = $ioFile;
    }

    /**
     * Cron that clean the logs after specific period
     */
    public function execute()
    {
        $retentionPeriod = (int) $this->accountConfig->getLogRetention();
        $logHandlerType = (int) $this->accountConfig->getLogHandler();

        if ($retentionPeriod) {
            if ($logHandlerType == LogHandler::TYPE_DB) {
                $this->proceedDb($retentionPeriod);
            }
            if ($logHandlerType == LogHandler::TYPE_FILES) {
                $this->proceedFiles($retentionPeriod);
            }
        }
        return $this;
    }

    /**
     * Delete logs from data base
     *
     * @param int $retentionPeriod
     * @return void
     */
    private function proceedDb(int $retentionPeriod)
    {
        try {
            $this->resourceConnection->getConnection()->delete(
                $this->resource->getMainTable(),
                ['time <= date_sub(now(),interval ' . $retentionPeriod . ' second)']
            );
        } catch (\Exception $e) {
            $this->logging->error('Proceed Db error:' . var_export($e->getMessage(), true));
        }
    }

    /**
     * Delete files that contains logs
     *
     * @param int $retentionPeriod
     * @return void
     * @throws FileSystemException
     */
    private function proceedFiles(int $retentionPeriod)
    {
        if ($files = $this->getAllFiles(DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'Buckaroo')) {
            $retentionTime = strtotime(gmdate('Y-m-d', time() - $retentionPeriod));
            foreach ($files as $file) {
                $fileInfo = $this->ioFile->getPathInfo($file);
                $fileName = $fileInfo['filename'];
                if (preg_match('/[\d]{4}\-\d{2}\-\d{2}/', $fileName)
                    && (strtotime($fileName) <= $retentionTime)) {
                    $this->driverFile->deleteFile($file);
                }
            }
        }
    }

    /**
     * Get all files from log directory
     *
     * @param string $path
     * @return array
     */
    private function getAllFiles(string $path): array
    {
        $paths = [];
        try {
            $path  = $this->directoryList->getPath('var') . $path;
            $paths = $this->driverFile->readDirectory($path);
        } catch (FileSystemException $e) {
            $this->logging->error('Get All Files error:' . var_export($e->getMessage(), true));
        }

        return $paths;
    }
}
