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
