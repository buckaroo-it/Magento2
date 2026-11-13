<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\Config\Source\LogHandler;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;
use Monolog\LogRecord;

class DebugHandler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '';

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @var DirectoryList
     */
    protected $dir;

    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @var DbHandler
     */
    private $dbHandler;

    /**
     * @param DriverInterface $filesystem
     * @param DirectoryList   $dir
     * @param Account         $accountConfig
     * @param DbHandler       $dbHandler
     */
    public function __construct(
        DriverInterface $filesystem,
        DirectoryList $dir,
        Account $accountConfig,
        DbHandler $dbHandler
    ) {
        $this->dir = $dir;
        $this->fileName = '/var/log/Buckaroo/' . date('Y-m-d') . '.log';
        $this->accountConfig = $accountConfig;
        $this->dbHandler = $dbHandler;

        parent::__construct($filesystem);
    }

    /**
     * Write log based on configuration (File, DB, or Both)
     *
     * @param mixed $record
     */
    public function write(mixed $record): void
    {
        $logHandlerType = (int) $this->accountConfig->getLogHandler();

        // Write to file if Files or Both
        if ($logHandlerType === LogHandler::TYPE_FILES || $logHandlerType === LogHandler::TYPE_BOTH) {
            parent::write($record);
        }

        // Write to database if DB or Both
        if ($logHandlerType === LogHandler::TYPE_DB || $logHandlerType === LogHandler::TYPE_BOTH) {
            $this->dbHandler->write($record);
        }
    }
}
