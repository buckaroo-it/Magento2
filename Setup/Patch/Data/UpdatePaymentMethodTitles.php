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
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

class UpdatePaymentMethodTitles implements DataPatchInterface
{
    private ModuleDataSetupInterface $setup;
    private ModuleDirReader $moduleDirReader;

    public function __construct(
        ModuleDataSetupInterface $setup,
        ModuleDirReader $moduleDirReader
    ) {
        $this->setup = $setup;
        $this->moduleDirReader = $moduleDirReader;
    }

    public function apply(): void
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
