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

/**
 * Merchants who had subtext configured before the display_subtext toggle was introduced
 * (BP-5211, shipped in v2.4.0) will find their subtext hidden after upgrading because
 * display_subtext defaults to 0 when no value exists in core_config_data.
 * This patch enables the toggle for every scope/store that already has a non-empty subtext.
 */
class EnableDisplaySubtextForExistingConfig implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        $subtextRows = $connection->fetchAll(
            $connection->select()
                ->from($configTable, ['scope', 'scope_id', 'path'])
                ->where('path LIKE ?', 'payment/buckaroo_magento2_%/subtext')
                ->where('value != ?', '')
        );

        foreach ($subtextRows as $row) {
            $displaySubtextPath = str_replace('/subtext', '/display_subtext', $row['path']);

            $existing = $connection->fetchOne(
                $connection->select()
                    ->from($configTable, ['config_id'])
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', $row['scope_id'])
                    ->where('path = ?', $displaySubtextPath)
            );

            if ($existing) {
                continue;
            }

            $connection->insert($configTable, [
                'scope'    => $row['scope'],
                'scope_id' => $row['scope_id'],
                'path'     => $displaySubtextPath,
                'value'    => '1',
            ]);
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
