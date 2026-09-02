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

class UpdateBuckarooAccountConfig implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();

        $path = 'buckaroo_magento2/account/active';
        $valueToUpdate = '1';

        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable('core_config_data'))
            ->where('path = ?', $path);

        $data = $connection->fetchRow($select);

        if ($data) {
            $connection->update(
                $this->moduleDataSetup->getTable('core_config_data'),
                ['value' => $valueToUpdate],
                ['path = ?' => $path]
            );
        } else {
            $connection->insert(
                $this->moduleDataSetup->getTable('core_config_data'),
                [
                    'scope' => 'default',
                    'scope_id' => 0,
                    'path' => $path,
                    'value' => $valueToUpdate
                ]
            );
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
