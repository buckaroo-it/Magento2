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

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Store the Click to Pay order amount limits where the availability check reads them.
 *
 * The admin fields saved to min_order_amount / max_order_amount, while every payment method's amount check reads
 * min_amount / max_amount, so the limits were never applied. The fields now save to min_amount / max_amount; this
 * patch moves the values already saved, per scope, unless that scope already has a value under the new path.
 */
class MoveClickToPayOrderAmountLimits implements DataPatchInterface
{
    public const PATHS = [
        'payment/buckaroo_magento2_clicktopay/min_order_amount' => 'payment/buckaroo_magento2_clicktopay/min_amount',
        'payment/buckaroo_magento2_clicktopay/max_order_amount' => 'payment/buckaroo_magento2_clicktopay/max_amount',
    ];

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

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
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        foreach (self::PATHS as $oldPath => $newPath) {
            $alreadySet = [];
            foreach ($connection->fetchAll(
                $connection->select()->from($configTable, ['scope', 'scope_id'])->where('path = ?', $newPath)
            ) as $row) {
                $alreadySet[$row['scope'] . '/' . (int)$row['scope_id']] = true;
            }

            $oldRows = $connection->fetchAll(
                $connection->select()->from($configTable, ['scope', 'scope_id', 'value'])->where('path = ?', $oldPath)
            );

            $newRows = [];
            foreach ($oldRows as $row) {
                if (!isset($alreadySet[$row['scope'] . '/' . (int)$row['scope_id']])) {
                    $newRows[] = [
                        'scope'    => $row['scope'],
                        'scope_id' => (int)$row['scope_id'],
                        'path'     => $newPath,
                        'value'    => $row['value'],
                    ];
                }
            }

            if ($newRows) {
                $connection->insertMultiple($configTable, $newRows);
            }
            $connection->delete($configTable, ['path = ?' => $oldPath]);
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
