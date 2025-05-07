<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world‑wide‑web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world‑wide‑web, please email
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

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\LogFactory;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;
use Monolog\LogRecord;

class DbHandler extends Base
{
    /**
     * @var LogFactory
     */
    protected LogFactory $logFactory;

    /**
     * @var int
     */
    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::DEBUG;

    /**
     * DbHandler constructor.
     */
    public function __construct(LogFactory $logFactory)
    {
        $this->logFactory = $logFactory;
    }

    /**
     * {@inheritDoc}
     *
     * Updated for Monolog 3.x compatibility – Magento 2.4.8+ now passes a
     * Monolog\LogRecord instead of a plain array.
     */
    public function write(LogRecord $record): void
    {
        $now = new \DateTime();

        $model = $this->logFactory->create();

        // Parse JSON‑encoded message if present
        try {
            $logData = json_decode($record->message, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $logData = [];
        }

        // Extract numeric/int level in a backward‑compatible way
        $levelValue = is_object($record->level) && property_exists($record->level, 'value')
            ? $record->level->value
            : (int) $record->level;

        $model->setData([
            'channel'     => $record->channel,
            'level'       => $levelValue,
            'message'     => $record->message,
            'time'        => $now->format('Y-m-d H:i:s'),
            'session_id'  => $logData['sid'] ?? '',
            'customer_id' => $logData['cid'] ?? '',
            'quote_id'    => $logData['qid'] ?? '',
            'order_id'    => $logData['id'] ?? '',
        ]);

        $model->save();
    }
}
