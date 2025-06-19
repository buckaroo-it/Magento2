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

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class MigrateSecondChanceModule implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Pool
     */
    private $cacheFrontendPool;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ModuleManager $moduleManager
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ModuleManager $moduleManager,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->moduleManager = $moduleManager;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Check if the old SecondChance module is enabled
        if ($this->moduleManager->isEnabled('Buckaroo_Magento2SecondChance')) {
            $this->handleSecondChanceMigration();
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Handle migration from separate SecondChance module
     */
    private function handleSecondChanceMigration()
    {
        $connection = $this->moduleDataSetup->getConnection();

        // Check if the table exists (old module was installed)
        $tableName = $this->moduleDataSetup->getTable('buckaroo_magento2_second_chance');
        
        if ($connection->isTableExists($tableName)) {
            // Table already exists from old module - this is good, we'll reuse it
            // Set a flag to indicate migration was performed
            $this->configWriter->save(
                'buckaroo_magento2/second_chance/migration_completed',
                '1'
            );

            // Create an admin notification about the migration
            $this->createMigrationNotification();
        }
    }

    /**
     * Create admin notification about SecondChance migration
     */
    private function createMigrationNotification()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $adminNotificationTable = $this->moduleDataSetup->getTable('adminnotification_inbox');

        if ($connection->isTableExists($adminNotificationTable)) {
            $data = [
                'severity' => 3, // Notice
                'date_added' => date('Y-m-d H:i:s'),
                'title' => 'Buckaroo SecondChance Module Migration',
                'description' => 'The Buckaroo SecondChance functionality has been integrated into the main Buckaroo Magento2 module. ' .
                               'The separate SecondChance module can now be safely disabled and removed. ' .
                               'All your existing SecondChance data and configuration have been preserved. ' .
                               'Please go to System > Configuration > Buckaroo > Second Chance to manage your settings.',
                'url' => 'admin/system_config/edit/section/buckaroo_magento2_second_chance',
                'is_read' => 0,
                'is_remove' => 0
            ];

            $connection->insert($adminNotificationTable, $data);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
} 