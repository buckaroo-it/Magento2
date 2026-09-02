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

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\ResourceModel\Log as LogResource;
use Magento\Framework\Logger\Handler\Base;
use Monolog\LogRecord;

class DbHandler extends Base
{
    /**
     * Bounds buffer memory on log-heavy requests; the rest flushes on close()
     */
    private const FLUSH_THRESHOLD = 100;

    /**
     * @var LogResource
     */
    private $logResource;

    /**
     * @var array[]
     */
    private $buffer = [];

    /**
     * Constructor.
     *
     * @param LogResource $logResource
     */
    public function __construct(LogResource $logResource)
    {
        $this->logResource = $logResource;
    }

    /**
     * Buffer the log record; rows are written in batch to avoid a full ORM
     * model save per log line. Accepts the Monolog 2 array or Monolog 3 LogRecord.
     *
     * @param mixed $record
     */
    public function write(mixed $record): void
    {
        if ($record instanceof LogRecord) {
            $record = $record->toArray();
        }

        $logData = $record['context'] ?? [];
        $now     = new \DateTimeImmutable('now');

        $this->buffer[] = [
            'channel'     => $record['channel'] ?? '',
            'level'       => $record['level'] ?? null,
            'message'     => $record['message'] ?? '',
            'time'        => $now->format('Y-m-d H:i:s'),
            'session_id'  => $logData['sid'] ?? '',
            'customer_id' => $logData['cid'] ?? '',
            'quote_id'    => $logData['qid'] ?? '',
            'order_id'    => $logData['id'] ?? '',
        ];

        if (count($this->buffer) >= self::FLUSH_THRESHOLD) {
            $this->flush();
        }
    }

    /**
     * Flush remaining buffered records; called by Monolog on shutdown.
     */
    public function close(): void
    {
        $this->flush();
    }

    /**
     * Write buffered rows in a single insert. Logging must never break the
     * request being logged, so failures fall back to the PHP system log.
     */
    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $rows = $this->buffer;
        $this->buffer = [];

        try {
            $this->logResource->getConnection()->insertMultiple(
                $this->logResource->getMainTable(),
                $rows
            );
        } catch (\Throwable $e) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            error_log('Buckaroo DbHandler: failed to persist ' . count($rows) . ' log rows: ' . $e->getMessage());
        }
    }
}
