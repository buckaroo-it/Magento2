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

namespace Buckaroo\Magento2\Model\SecondChance;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\UrlInterface;

class ModuleConflictDetector
{
    const OLD_MODULE_NAME = 'Buckaroo_Magento2SecondChance';
    const CONFIG_PATH_MIGRATION_COMPLETED = 'buckaroo_magento2/second_chance/migration_completed';

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var MessageManager
     */
    private $messageManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ModuleManager        $moduleManager
     * @param ScopeConfigInterface $scopeConfig
     * @param MessageManager       $messageManager
     * @param UrlInterface         $urlBuilder
     */
    public function __construct(
        ModuleManager $moduleManager,
        ScopeConfigInterface $scopeConfig,
        MessageManager $messageManager,
        UrlInterface $urlBuilder
    ) {
        $this->moduleManager = $moduleManager;
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Check if old SecondChance module is still enabled
     *
     * @return bool
     */
    public function isOldModuleEnabled(): bool
    {
        return $this->moduleManager->isEnabled(self::OLD_MODULE_NAME);
    }

    /**
     * Check if migration has been completed
     *
     * @return bool
     */
    public function isMigrationCompleted(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::CONFIG_PATH_MIGRATION_COMPLETED);
    }

    /**
     * Show admin notification about module conflict
     */
    public function showConflictNotification(): void
    {
        if ($this->isOldModuleEnabled()) {
            $configUrl = $this->urlBuilder->getUrl('adminhtml/system_config/edit', [
                'section' => 'buckaroo_magento2_second_chance'
            ]);

            $message = __(
                'The separate Buckaroo SecondChance module is still enabled. ' .
                'SecondChance functionality is now integrated into the main Buckaroo module. ' .
                'Please disable the separate SecondChance module to avoid conflicts. ' .
                '<a href="%1">Configure SecondChance settings here</a>.',
                $configUrl
            );

            $this->messageManager->addWarningMessage($message);
        }
    }

    /**
     * Get migration instructions for users
     *
     * @return array
     */
    public function getMigrationInstructions(): array
    {
        return [
            'steps' => [
                'Go to System > Configuration > Advanced > Advanced > Disable Modules',
                'Find "Buckaroo_Magento2SecondChance" and disable it',
                'Run "php bin/magento module:disable Buckaroo_Magento2SecondChance"',
                'Run "php bin/magento setup:upgrade"',
                'Clear cache: "php bin/magento cache:clean"',
                'Remove the module: "composer remove buckaroo/magento2secondchance"',
                'Configure SecondChance in System > Configuration > Buckaroo > Second Chance'
            ],
            'data_preservation' => 'All your existing SecondChance data will be preserved automatically.',
            'configuration_location' => 'System > Configuration > Buckaroo > Second Chance'
        ];
    }

    /**
     * Check for conflicts and show notifications if needed
     */
    public function checkAndNotify(): void
    {
        if ($this->isOldModuleEnabled() && !$this->isMigrationCompleted()) {
            $this->showConflictNotification();
        }
    }
}
