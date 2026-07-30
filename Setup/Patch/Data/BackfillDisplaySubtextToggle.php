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

use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillDisplaySubtextToggle implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * Constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ModuleDirReader          $moduleDirReader
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ModuleDirReader $moduleDirReader
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->moduleDirReader = $moduleDirReader;
    }

    /**
     * Backfill the display_subtext toggle to enabled for every Buckaroo payment method scope.
     *
     * @return $this
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        foreach ($this->getPaymentMethodCodes() as $methodCode) {
            $pathPrefix = sprintf('payment/%s/', $methodCode);
            $displaySubtextPath = sprintf('%sdisplay_subtext', $pathPrefix);

            $scopesForMethod = $connection->fetchAll(
                $connection->select()
                    ->from($configTable, ['scope', 'scope_id'])
                    ->where('path LIKE ?', $pathPrefix . '%')
                    ->group(['scope', 'scope_id'])
            );

            // Always include default scope for methods that only rely on config.xml defaults.
            $scopesForMethod[] = ['scope' => 'default', 'scope_id' => 0];

            $uniqueScopes = [];
            foreach ($scopesForMethod as $scopeRow) {
                $uniqueScopes[$scopeRow['scope'] . ':' . (int)$scopeRow['scope_id']] = [
                    'scope' => (string)$scopeRow['scope'],
                    'scope_id' => (int)$scopeRow['scope_id'],
                ];
            }

            foreach ($uniqueScopes as $scopeRow) {
                $toggleExists = (bool)$connection->fetchOne(
                    $connection->select()
                        ->from($configTable, ['config_id'])
                        ->where('path = ?', $displaySubtextPath)
                        ->where('scope = ?', $scopeRow['scope'])
                        ->where('scope_id = ?', $scopeRow['scope_id'])
                );

                if ($toggleExists) {
                    $connection->update(
                        $configTable,
                        ['value' => '1'],
                        [
                            'path = ?' => $displaySubtextPath,
                            'scope = ?' => $scopeRow['scope'],
                            'scope_id = ?' => $scopeRow['scope_id'],
                        ]
                    );
                    continue;
                }

                $connection->insert(
                    $configTable,
                    [
                        'scope' => $scopeRow['scope'],
                        'scope_id' => $scopeRow['scope_id'],
                        'path' => $displaySubtextPath,
                        'value' => '1',
                    ]
                );
            }
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * Return the data patches this patch depends on.
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Return the aliases for this patch.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Read the Buckaroo payment method codes from the module config.xml defaults.
     *
     * @return string[]
     */
    private function getPaymentMethodCodes(): array
    {
        $configPath = $this->moduleDirReader->getModuleDir('etc', 'Buckaroo_Magento2') . '/config.xml';
        $config = simplexml_load_file($configPath);

        if ($config === false || !isset($config->default->payment)) {
            return [];
        }

        $methodCodes = [];
        foreach ($config->default->payment->children() as $methodNode) {
            $methodCodes[] = (string)$methodNode->getName();
        }

        return $methodCodes;
    }
}
