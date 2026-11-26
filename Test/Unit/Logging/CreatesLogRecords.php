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

use Monolog\Logger;

/**
 * Builds a log record for Monolog 2 (array) and Monolog 3 (LogRecord), both allowed by composer.json.
 */
trait CreatesLogRecords
{
    /**
     * @param int    $level   One of the Monolog\Logger level constants
     * @param string $message
     *
     * @return array|\Monolog\LogRecord
     */
    private function createRecord(int $level, string $message)
    {
        if (class_exists(\Monolog\LogRecord::class)) {
            return new \Monolog\LogRecord(
                new \DateTimeImmutable(),
                'buckaroo',
                \Monolog\Level::from($level),
                $message
            );
        }

        return [
            'message'    => $message,
            'context'    => [],
            'level'      => $level,
            'level_name' => Logger::getLevelName($level),
            'channel'    => 'buckaroo',
            'datetime'   => new \DateTimeImmutable(),
            'extra'      => [],
        ];
    }
}
