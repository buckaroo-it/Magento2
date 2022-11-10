<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

use Magento\Framework\EntityManager\MetadataPool;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\ResourceConnection;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\LogHandler;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Io\File as IoFile;

class LogCleaner
{
    private $resource;
    private $accountConfig;
    private $resourceConnection;
    private $logging;
    private $directoryList;
    private $driverFile;
    private $ioFile;

    public function __construct(
        \Buckaroo\Magento2\Model\ResourceModel\Log $resource,
        Account $accountConfig,
        ResourceConnection $resourceConnection,
        Log $logging,
        DirectoryList $directoryList,
        File $driverFile,
        IoFile $ioFile
    ) {
        $this->resource = $resource;
        $this->accountConfig       = $accountConfig;
        $this->resourceConnection  = $resourceConnection;
        $this->logging             = $logging;
        $this->directoryList       = $directoryList;
        $this->driverFile          = $driverFile;
        $this->ioFile              = $ioFile;
    }
    
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

    private function proceedDb(int $retentionPeriod)
    {
        try {
            $this->resourceConnection->getConnection()->delete(
                $this->resource->getMainTable(),
                ['time <= date_sub(now(),interval ' . $retentionPeriod . ' second)']
            );
        } catch (\Exception $e) {
            $this->logging->error('Proceed Db error:'. var_export($e->getMessage(), true));
        }
    }

    private function proceedFiles(int $retentionPeriod)
    {
        if ($files = $this->getAllFiles(DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'Buckaroo')) {
            $retentionTime = strtotime(gmdate('Y-m-d', time() - $retentionPeriod));
            foreach ($files as $file) {
                $fileInfo = $this->ioFile->getPathInfo($file);
                $fileName = $fileInfo['filename'];
                $matches = null;
                if (preg_match('/[\d]{4}\-\d{2}\-\d{2}/', $fileName, $matches)) {
                    if (strtotime($fileName) <= $retentionTime) {
                        $this->driverFile->deleteFile($file);
                    }
                }
            }
        }
    }

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
