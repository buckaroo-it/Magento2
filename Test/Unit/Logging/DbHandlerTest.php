<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Logging;

use Buckaroo\Magento2\Logging\DbHandler;
use Buckaroo\Magento2\Model\ResourceModel\Log as LogResource;
use Magento\Framework\DB\Adapter\AdapterInterface;

class DbHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = DbHandler::class;

    private $logResourceMock;
    private $connectionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->logResourceMock = $this->createMock(LogResource::class);
        $this->logResourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->logResourceMock->method('getMainTable')->willReturn('buckaroo_magento2_log');
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + ['logResource' => $this->logResourceMock]);
    }

    public function testWriteBuffersRecordsAndCloseFlushesThemInOneInsert(): void
    {
        $instance = $this->getInstance();

        $this->connectionMock->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'buckaroo_magento2_log',
                $this->callback(function (array $rows): bool {
                    return count($rows) === 2
                        && $rows[0]['message'] === 'first message'
                        && $rows[0]['channel'] === 'buckaroo'
                        && $rows[0]['session_id'] === 'sid-1'
                        && $rows[1]['message'] === 'second message';
                })
            );

        $instance->write([
            'channel' => 'buckaroo',
            'level'   => 200,
            'message' => 'first message',
            'context' => ['sid' => 'sid-1'],
        ]);
        $instance->write([
            'channel' => 'buckaroo',
            'level'   => 200,
            'message' => 'second message',
            'context' => [],
        ]);

        $instance->close();
        // A second close must not insert again (buffer already flushed)
        $instance->close();
    }

    public function testWriteFlushesWhenThresholdReached(): void
    {
        $instance = $this->getInstance();

        $this->connectionMock->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'buckaroo_magento2_log',
                $this->callback(fn (array $rows) => count($rows) === 100)
            );

        for ($i = 0; $i < 100; $i++) {
            $instance->write(['channel' => 'buckaroo', 'level' => 100, 'message' => "line {$i}", 'context' => []]);
        }
    }

    public function testFlushFailureIsSwallowedAndNeverThrows(): void
    {
        $instance = $this->getInstance();

        $this->connectionMock->method('insertMultiple')
            ->willThrowException(new \RuntimeException('db gone'));

        $instance->write(['channel' => 'buckaroo', 'level' => 400, 'message' => 'boom', 'context' => []]);

        $previousErrorLog = ini_set('error_log', '/dev/null');
        try {
            $instance->close();
            $this->assertTrue(true);
        } finally {
            ini_set('error_log', (string)$previousErrorLog);
        }
    }
}
