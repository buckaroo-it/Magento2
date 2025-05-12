<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Logging;

use Magento\Framework\Logger\Handler\Base;
use Monolog\LogRecord;
use Monolog\Logger;
use Buckaroo\Magento2\Model\LogFactory;

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
     * Persist a log record in the Buckaroo log table.
     *
     * Magento ≤ 2.4 .7 ships with Monolog 2 → $record is an **array**.
     * Magento 2.4 .8+ uses Monolog 3      → $record is a **LogRecord** object.
     *
     * We therefore accept *mixed* and normalise it at runtime.
     *
     * @param mixed $record
     */
    public function write($record): void              // ← compatible
    {
        // ───────────────────────────────────────────────────────
        // 1. Convert LogRecord → array so the rest of the code
        //    can stay unchanged.
        // ───────────────────────────────────────────────────────
        if ($record instanceof LogRecord) {
            $record = $record->toArray();
        }

        // 2. Normalise a few helpers so the original code that
        //    followed continues to work as-is.
        $levelValue = $record['level']      ?? null;            // your previous code sets this
        $logData    = $record['context']    ?? [];              // ctx array (sid, cid …)
        $now        = new \DateTimeImmutable('now');

        /* ------------------------------------------------------
         * 3. Everything BELOW is what you already had – no logic
         *    was removed.  Keep / modify as you like.
         * ---------------------------------------------------- */

         $model = $this->buckarooLogFactory->create();   // ← existing dependency
         $model->setData([
             'channel'     => $record['channel'] ?? '',
             'level'       => $levelValue,
             'message'     => $record['message'] ?? '',
             'time'        => $now->format('Y-m-d H:i:s'),
             'session_id'  => $logData['sid'] ?? '',
             'customer_id' => $logData['cid'] ?? '',
             'quote_id'    => $logData['qid'] ?? '',
             'order_id'    => $logData['id'] ?? '',
         ]);
         $model->save();
     }
}
