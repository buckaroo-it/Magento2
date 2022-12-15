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

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class DebugHandler extends Base
{
    // @codingStandardsIgnoreLine
    protected $loggerType = Logger::DEBUG;

    // @codingStandardsIgnoreLine
    protected $fileName = '';

    protected $filesystem;

    protected $dir;

    public function __construct(
        DriverInterface $filesystem,
        DirectoryList $dir
    ) {
        $this->dir      = $dir;
        $this->fileName = '/var/log/Buckaroo/' . date('Y-m-d') . '.log';

        parent::__construct($filesystem);
    }
}
