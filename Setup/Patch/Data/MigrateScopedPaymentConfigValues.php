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

class MigrateScopedPaymentConfigValues implements DataPatchInterface
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
        $configTable = $this->moduleDataSetup->getTable('core_config_data');

        foreach ($this->getPathMappings() as $oldPath => $newPath) {
            $sourceRows = $connection->fetchAll(
                $connection->select()
                    ->from($configTable, ['scope', 'scope_id', 'value'])
                    ->where('path = ?', $oldPath)
            );

            foreach ($sourceRows as $sourceRow) {
                $targetExists = (bool)$connection->fetchOne(
                    $connection->select()
                        ->from($configTable, ['config_id'])
                        ->where('path = ?', $newPath)
                        ->where('scope = ?', $sourceRow['scope'])
                        ->where('scope_id = ?', (int)$sourceRow['scope_id'])
                );

                if ($targetExists) {
                    continue;
                }

                $connection->insert(
                    $configTable,
                    [
                        'scope' => $sourceRow['scope'],
                        'scope_id' => (int)$sourceRow['scope_id'],
                        'path' => $newPath,
                        'value' => $sourceRow['value'],
                    ]
                );
            }
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

    /**
     * Maps legacy config paths to current paths.
     *
     * Migration is intentionally conservative: values are copied only when the
     * new path is missing for the same scope/scope_id.
     *
     * @return array<string, string>
     */
    private function getPathMappings(): array
    {
        return [
            // Riverty (legacy AfterPay method codes -> current method code).
            'payment/buckaroo_magento2_afterpay/subtext' => 'payment/buckaroo_magento2_afterpay20/subtext',
            'payment/buckaroo_magento2_afterpay/subtext_style' => 'payment/buckaroo_magento2_afterpay20/subtext_style',
            'payment/buckaroo_magento2_afterpay/subtext_color' => 'payment/buckaroo_magento2_afterpay20/subtext_color',
            'payment/buckaroo_magento2_afterpay/display_subtext' => 'payment/buckaroo_magento2_afterpay20/display_subtext',
            'payment/buckaroo_magento2_afterpay2/subtext' => 'payment/buckaroo_magento2_afterpay20/subtext',
            'payment/buckaroo_magento2_afterpay2/subtext_style' => 'payment/buckaroo_magento2_afterpay20/subtext_style',
            'payment/buckaroo_magento2_afterpay2/subtext_color' => 'payment/buckaroo_magento2_afterpay20/subtext_color',
            'payment/buckaroo_magento2_afterpay2/display_subtext' => 'payment/buckaroo_magento2_afterpay20/display_subtext',

            // Hosted Fields settings (legacy creditcard code -> current creditcards code).
            'payment/buckaroo_magento2_creditcard/placeholder_cardholder_name'
            => 'payment/buckaroo_magento2_creditcards/placeholder_cardholder_name',
            'payment/buckaroo_magento2_creditcard/placeholder_card_number'
            => 'payment/buckaroo_magento2_creditcards/placeholder_card_number',
            'payment/buckaroo_magento2_creditcard/placeholder_expiry_date'
            => 'payment/buckaroo_magento2_creditcards/placeholder_expiry_date',
            'payment/buckaroo_magento2_creditcard/placeholder_cvc'
            => 'payment/buckaroo_magento2_creditcards/placeholder_cvc',
            'payment/buckaroo_magento2_creditcard/field_text_color'
            => 'payment/buckaroo_magento2_creditcards/field_text_color',
            'payment/buckaroo_magento2_creditcard/field_background_color'
            => 'payment/buckaroo_magento2_creditcards/field_background_color',
            'payment/buckaroo_magento2_creditcard/field_border_color'
            => 'payment/buckaroo_magento2_creditcards/field_border_color',
            'payment/buckaroo_magento2_creditcard/field_placeholder_color'
            => 'payment/buckaroo_magento2_creditcards/field_placeholder_color',
            'payment/buckaroo_magento2_creditcard/field_font_size'
            => 'payment/buckaroo_magento2_creditcards/field_font_size',
            'payment/buckaroo_magento2_creditcard/field_font_family'
            => 'payment/buckaroo_magento2_creditcards/field_font_family',
            'payment/buckaroo_magento2_creditcard/field_border_radius'
            => 'payment/buckaroo_magento2_creditcards/field_border_radius',
        ];
    }
}
