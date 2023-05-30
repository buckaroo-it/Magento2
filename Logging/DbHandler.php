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

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\LogFactory;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class DbHandler extends Base
{
    /**
     * @var \Buckaroo\Magento2\Model\Log
     */
    protected $logFactory;

    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::DEBUG;

    /**
     * @param LogFactory $logFactory
     */
    public function __construct(
        LogFactory $logFactory
    ) {
        $this->logFactory = $logFactory;
    }

    /**
     * @inheritdoc
     */
    public function write(array $record): void
    {
        $now = new \DateTime();
        $logFactory = $this->logFactory->create();
        try {
            $logData = json_decode($record['message'], true);
        } catch (\Exception $e) {
            $logData = [];
        }

        $logFactory->setData([
            'channel'     => $record['channel'],
            'level'       => $record['level'],
            'message'     => $record['message'],
            'time'        => $now->format('Y-m-d H:i:s'),
            'session_id'  => ($logData['sid']) ?? '',
            'customer_id' => ($logData['cid']) ?? '',
            'quote_id'    => ($logData['qid']) ?? '',
            'order_id'    => ($logData['id']) ?? ''
        ]);
        $logFactory->save();
    }
}
