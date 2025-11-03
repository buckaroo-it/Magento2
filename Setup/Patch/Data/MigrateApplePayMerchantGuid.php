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

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Migrate Apple Pay Merchant GUID from v1.5x.x to v2.0.x configuration structure
 */
class MigrateApplePayMerchantGuid implements DataPatchInterface
{
    /**
     * Current configuration path for merchant GUID in v2.0.x
     */
    private const NEW_GUID_PATH = 'payment/buckaroo_magento2_applepay/merchant_guid';

    /**
     * Old configuration path from v1.5x.x (before refactoring)
     * In v1.5x, the GUID was stored in the general account section, not payment-method specific
     */
    private const OLD_GUID_PATH = 'buckaroo_magento2/account/merchant_guid';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface          $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        // Get all existing configurations for the new GUID path
        $select = $connection->select()
            ->from($configTable, ['scope', 'scope_id', 'path', 'value'])
            ->where('path = ?', self::NEW_GUID_PATH);

        $existingNewConfigs = $connection->fetchAll($select);

        // For each scope (default, website, store), check if GUID needs migration
        $scopesToCheck = [
            ['scope' => 'default', 'scope_id' => 0],
        ];

        // Also check for website and store level configurations
        $websiteSelect = $connection->select()
            ->from($this->moduleDataSetup->getTable('store_website'), ['website_id']);
        $websites = $connection->fetchCol($websiteSelect);
        
        foreach ($websites as $websiteId) {
            $scopesToCheck[] = ['scope' => 'websites', 'scope_id' => (int)$websiteId];
        }

        $storeSelect = $connection->select()
            ->from($this->moduleDataSetup->getTable('store'), ['store_id'])
            ->where('store_id != ?', 0); // Exclude admin store
        $stores = $connection->fetchCol($storeSelect);
        
        foreach ($stores as $storeId) {
            $scopesToCheck[] = ['scope' => 'stores', 'scope_id' => (int)$storeId];
        }

        // Process each scope
        foreach ($scopesToCheck as $scopeData) {
            $scope = $scopeData['scope'];
            $scopeId = $scopeData['scope_id'];

            // Check if the new path already has a value for this scope
            $hasNewValue = false;
            foreach ($existingNewConfigs as $config) {
                if ($config['scope'] === $scope 
                    && (int)$config['scope_id'] === $scopeId 
                    && !empty($config['value'])
                ) {
                    $hasNewValue = true;
                    break;
                }
            }

            // If new path is empty, try to migrate from old path
            if (!$hasNewValue) {
                // Check for value in old v1.5x path
                $oldSelect = $connection->select()
                    ->from($configTable, ['value'])
                    ->where('path = ?', self::OLD_GUID_PATH)
                    ->where('scope = ?', $scope)
                    ->where('scope_id = ?', $scopeId);

                $migratedValue = $connection->fetchOne($oldSelect);

                // If we found a value in the old location, migrate it
                if ($migratedValue !== null && $migratedValue !== false) {
                    try {
                        // Check if record exists in new location
                        $checkSelect = $connection->select()
                            ->from($configTable, ['config_id'])
                            ->where('path = ?', self::NEW_GUID_PATH)
                            ->where('scope = ?', $scope)
                            ->where('scope_id = ?', $scopeId);

                        $existingConfigId = $connection->fetchOne($checkSelect);

                        if ($existingConfigId) {
                            // Update existing record
                            $connection->update(
                                $configTable,
                                ['value' => $migratedValue],
                                [
                                    'config_id = ?' => $existingConfigId
                                ]
                            );
                            $this->logger->info(
                                sprintf(
                                    'Buckaroo: Updated Apple Pay Merchant GUID for scope "%s" (ID: %d) from old path "%s"',
                                    $scope,
                                    $scopeId,
                                    self::OLD_GUID_PATH
                                )
                            );
                        } else {
                            // Insert new record
                            $connection->insert(
                                $configTable,
                                [
                                    'scope' => $scope,
                                    'scope_id' => $scopeId,
                                    'path' => self::NEW_GUID_PATH,
                                    'value' => $migratedValue
                                ]
                            );
                            $this->logger->info(
                                sprintf(
                                    'Buckaroo: Migrated Apple Pay Merchant GUID for scope "%s" (ID: %d) from old path "%s"',
                                    $scope,
                                    $scopeId,
                                    self::OLD_GUID_PATH
                                )
                            );
                        }
                    } catch (\Exception $e) {
                        $this->logger->error(
                            sprintf(
                                'Buckaroo: Failed to migrate Apple Pay Merchant GUID for scope "%s" (ID: %d): %s',
                                $scope,
                                $scopeId,
                                $e->getMessage()
                            )
                        );
                    }
                }
            }
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [
            UpdateBuckarooAccountConfig::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}

