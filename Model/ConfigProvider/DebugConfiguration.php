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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;

class DebugConfiguration extends AbstractConfigProvider
{
    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Account              $account
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Account $account
    ) {
        $this->accountConfig = $account;
        parent::__construct($scopeConfig);
    }

    /**
     * Get Log level
     *
     * @return mixed
     */
    public function getLogLevel()
    {
        return (string)$this->accountConfig->getLogLevel();
    }

    /**
     * Is Logger active
     *
     * @param int|string $level
     *
     * @return bool
     */
    public function canLog($level)
    {
        $logTypes = explode(',', $this->getLoglevel());
        return in_array($level, $logTypes);
    }

    /**
     * Get Debug backtrace logging depth
     *
     * @return mixed
     */
    public function getDebugBacktraceDepth()
    {
        return $this->accountConfig->getLogDbtraceDepth();
    }
}
