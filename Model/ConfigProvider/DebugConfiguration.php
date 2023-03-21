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
namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;

class DebugConfiguration extends AbstractConfigProvider
{
    /** @var Account */
    private $accountConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Account $account
    ) {
        $this->accountConfig = $account;

        parent::__construct($scopeConfig);
    }

    /**
     * @return mixed
     */
    public function getDebugTypes()
    {
        return $this->accountConfig->getDebugTypes();
    }

    /**
     * @return array
     */
    public function getDebugEmails()
    {
        $debugEmails = $this->accountConfig->getDebugEmail();
        if (!is_scalar($debugEmails)) {
            return [];
        }

        $debugEmails = explode(',', preg_replace('/\s+/', '', (string)$debugEmails));

        return array_filter($debugEmails, function ($debugEmail) {
            return filter_var($debugEmail, FILTER_VALIDATE_EMAIL);
        });
    }

    /**
     * @param $level
     *
     * @return bool
     */
    public function canLog($level)
    {
        $logTypes = explode(',', (string)$this->getDebugTypes());
        return in_array($level, $logTypes);
    }
}
