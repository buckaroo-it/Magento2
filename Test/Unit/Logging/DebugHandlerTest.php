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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Logging;

use Buckaroo\Magento2\Logging\DbHandler;
use Buckaroo\Magento2\Logging\DebugHandler;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DebugHandlerTest extends TestCase
{
    use CreatesLogRecords;

    /**
     * @var string
     */
    private $logFile;

    /**
     * @var Account|MockObject
     */
    private $accountConfig;

    /**
     * @var DbHandler|MockObject
     */
    private $dbHandler;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/buckaroo-debug-handler-' . uniqid('', true) . '.log';
        $this->accountConfig = $this->createMock(Account::class);
        $this->dbHandler = $this->createMock(DbHandler::class);
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testLogsToTheBuckarooLogFileOfToday(): void
    {
        $handler = new DebugHandler(
            $this->createMock(DriverInterface::class),
            $this->createMock(DirectoryList::class),
            $this->accountConfig,
            $this->dbHandler
        );

        $this->assertStringStartsWith(BP . '/', $handler->getUrl());
        $this->assertStringEndsWith('/var/log/Buckaroo/' . date('Y-m-d') . '.log', $handler->getUrl());
    }

    /**
     * Log handler setting: 1 = files, 2 = database, 3 = both
     *
     * @return array
     */
    public static function routingProvider(): array
    {
        return [
            'files only'      => ['1', true, false],
            'database only'   => ['2', false, true],
            'files and db'    => ['3', true, true],
            'unknown setting' => ['0', false, false],
        ];
    }

    #[DataProvider('routingProvider')]
    public function testWritesWhereTheLogHandlerSettingSays(string $setting, bool $toFile, bool $toDb): void
    {
        $this->accountConfig->method('getLogHandler')->willReturn($setting);
        $record = $this->createRecord(Logger::DEBUG, 'routing message');

        $this->dbHandler->expects($toDb ? $this->once() : $this->never())
            ->method('write')
            ->with($this->callback(function ($written) {
                // Monolog 2 passes a processed copy of the array record, Monolog 3 the same object
                return $written['message'] === 'routing message' && (int)$written['level'] === Logger::DEBUG;
            }));

        $this->createHandler()->handle($record);

        $this->assertSame($toFile, $this->fileContains('routing message'));
    }

    public function testHandlesDebugRecords(): void
    {
        $this->accountConfig->method('getLogHandler')->willReturn('1');

        $handler = $this->createHandler();

        $this->assertTrue($handler->isHandling($this->createRecord(Logger::DEBUG, 'x')));
        $handler->handle($this->createRecord(Logger::DEBUG, 'debug message'));

        $this->assertTrue($this->fileContains('debug message'));
    }

    /**
     * Handler writing to a temporary file instead of the shop's log directory
     *
     * @return DebugHandler
     */
    private function createHandler(): DebugHandler
    {
        $filesystem = $this->createMock(DriverInterface::class);
        $filesystem->method('getParentDirectory')->willReturnCallback('dirname');
        $filesystem->method('isDirectory')->willReturn(true);

        $handler = new DebugHandler(
            $filesystem,
            $this->createMock(DirectoryList::class),
            $this->accountConfig,
            $this->dbHandler
        );
        (new \ReflectionProperty(StreamHandler::class, 'url'))->setValue($handler, $this->logFile);

        return $handler;
    }

    private function fileContains(string $message): bool
    {
        return is_file($this->logFile) && strpos((string)file_get_contents($this->logFile), $message) !== false;
    }
}
