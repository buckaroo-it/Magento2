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
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

class UpdatePaymentMethodTitles implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleDirReader          $moduleDirReader
     */
    public function __construct(
        ModuleDataSetupInterface $setup,
        ModuleDirReader $moduleDirReader
    ) {
        $this->setup = $setup;
        $this->moduleDirReader = $moduleDirReader;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->setup->startSetup();
        $connection = $this->setup->getConnection();

        $configPath = $this->moduleDirReader->getModuleDir('etc', 'Buckaroo_Magento2') . '/config.xml';
        $config = simplexml_load_file($configPath);

        $methods = [];
        foreach ($config->default->payment->children() as $methodCode => $methodData) {
            if (isset($methodData->title)) {
                $methods[$methodCode] = (string)$methodData->title;
            }
        }

        foreach ($methods as $code => $label) {
            $path = "payment/{$code}/title";

            $select = $connection->select()
                ->from($this->setup->getTable('core_config_data'))
                ->where('path = ?', $path);

            $data = $connection->fetchRow($select);

            if ($data) {
                $connection->update(
                    $this->setup->getTable('core_config_data'),
                    ['value' => $label],
                    ['path = ?' => $path]
                );
            }
        }

        $this->setup->endSetup();

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
