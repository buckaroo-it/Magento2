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

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class CriticalHandler extends Base
{
    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::CRITICAL;

    // @codingStandardsIgnoreLine
    protected $fileName = '/var/log/Buckaroo/critical.log';
}
