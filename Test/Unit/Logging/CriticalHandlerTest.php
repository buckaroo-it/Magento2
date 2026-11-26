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

use Buckaroo\Magento2\Logging\CriticalHandler;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CriticalHandlerTest extends TestCase
{
    use CreatesLogRecords;

    /**
     * @var string
     */
    private $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/buckaroo-critical-handler-' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testLogsToTheBuckarooCriticalLog(): void
    {
        $handler = new CriticalHandler($this->createMock(DriverInterface::class));

        $this->assertStringStartsWith(BP . '/', $handler->getUrl());
        $this->assertStringEndsWith('/var/log/Buckaroo/critical.log', $handler->getUrl());
    }

    /**
     * @return array
     */
    public static function levelProvider(): array
    {
        return [
            'debug'     => [Logger::DEBUG, false],
            'error'     => [Logger::ERROR, false],
            'critical'  => [Logger::CRITICAL, true],
            'emergency' => [Logger::EMERGENCY, true],
        ];
    }

    #[DataProvider('levelProvider')]
    public function testOnlyWritesCriticalAndMoreSevereRecords(int $level, bool $written): void
    {
        $filesystem = $this->createMock(DriverInterface::class);
        $filesystem->method('getParentDirectory')->willReturnCallback('dirname');
        $filesystem->method('isDirectory')->willReturn(true);

        $handler = new CriticalHandler($filesystem);
        // Write to a temporary file instead of the shop's log directory
        (new \ReflectionProperty(StreamHandler::class, 'url'))->setValue($handler, $this->logFile);

        $handler->handle($this->createRecord($level, 'level message'));

        $this->assertSame(
            $written,
            is_file($this->logFile) && strpos((string)file_get_contents($this->logFile), 'level message') !== false
        );
    }
}
