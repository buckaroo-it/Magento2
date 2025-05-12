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

declare(strict_types=1);

namespace Buckaroo\Magento2\Logging;

use Magento\Framework\Logger\Handler\Base;
use Monolog\LogRecord;
use Buckaroo\Magento2\Model\LogFactory;

class DbHandler extends Base
{
    private LogFactory $logFactory;

    public function __construct(LogFactory $logFactory)
    {
        $this->logFactory = $logFactory;
    }

    /**
     * Accepts either the Monolog 2 array or the Monolog 3 LogRecord object.
     */
    public function write(mixed $record): void
    {
        if ($record instanceof LogRecord) {
            $record = $record->toArray();   // normalise
        }

        $levelValue = $record['level']   ?? null;
        $logData    = $record['context'] ?? [];
        $now        = new \DateTimeImmutable('now');

        $model = $this->logFactory->create();
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
