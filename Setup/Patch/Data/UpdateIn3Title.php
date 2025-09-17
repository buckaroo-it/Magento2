<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateIn3Title implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        $path = 'payment/buckaroo_magento2_capayablein3/title';
        $value = 'In3';

        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable('core_config_data'))
            ->where('path = ?', $path);

        $data = $connection->fetchRow($select);

        if ($data) {
            $connection->update(
                $this->moduleDataSetup->getTable('core_config_data'),
                ['value' => $value],
                ['path = ?' => $path]
            );
        }

        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
