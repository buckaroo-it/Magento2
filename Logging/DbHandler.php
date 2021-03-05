<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class DbHandler extends Base
{
    protected $logFactory;

    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::DEBUG;

    /**
     * @param DriverInterface $filesystem
     * @param string $filePath
     * @param string $fileName
     * @throws \Exception
     */
    public function __construct(
        \Buckaroo\Magento2\Model\LogFactory $logFactory
    ) {
        $this->logFactory = $logFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        $now = new \DateTime();
        $logFactory = $this->logFactory->create();
        $logFactory->setData([
            'channel'     => $record['channel'],
            'level'       => $record['level'],
            'message'     => $record['message'],
            'time'        => $now->format('Y-m-d H:i:s'),
            'session_id'  => isset($record['extra']['session_id']) ? $record['extra']['session_id'] : '',
            'customer_id' => isset($record['extra']['customer_id']) ? $record['extra']['customer_id'] : '',
            'quote_id'    => isset($record['extra']['quote_id']) ? $record['extra']['quote_id'] : '',
            'order_id'    => isset($record['extra']['order_id']) ? $record['extra']['order_id'] : '',
        ]);
        $logFactory->save();
    }
}
