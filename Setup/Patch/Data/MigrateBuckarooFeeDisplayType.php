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

use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Keep the Buckaroo fee shown the way it was before the fee display setting was read.
 *
 * The order, invoice, credit memo and email totals used to decide "Incl. Tax" or "Excl. Tax" from the
 * fee tax class id: tax class 2 showed the fee including tax, any other class excluding tax. They now
 * read tax/sales_display/buckaroo_fee instead. This patch writes that setting for every scope that has
 * a fee tax class, with the display type that scope showed so far, so nothing changes for the shopper
 * or the merchant. A display setting that already exists for a scope is left alone.
 */
class MigrateBuckarooFeeDisplayType implements DataPatchInterface
{
    /**
     * The tax class id that made the totals show the fee including tax
     */
    private const TAX_CLASS_SHOWN_INCLUDING_TAX = '2';

    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;

    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function __construct(ModuleDataSetupInterface $setup)
    {
        $this->setup = $setup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->setup->startSetup();
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable('core_config_data');

        $taxClassRows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['scope', 'scope_id', 'value'])
                ->where('path = ?', BuckarooFee::XPATH_ACCOUNT_BUCKAROO_FEE_TAX_CLASS)
        );

        foreach ($taxClassRows as $row) {
            $exists = $connection->fetchOne(
                $connection->select()
                    ->from($table, ['config_id'])
                    ->where('path = ?', BuckarooFee::XPATH_SALES_DISPLAY_BUCKAROO_FEE)
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', (int)$row['scope_id'])
            );
            if ($exists) {
                continue;
            }

            $displayType = trim((string)$row['value']) === self::TAX_CLASS_SHOWN_INCLUDING_TAX
                ? DisplayType::DISPLAY_TYPE_INCLUDING_TAX
                : DisplayType::DISPLAY_TYPE_EXCLUDING_TAX;

            $connection->insert($table, [
                'scope'    => $row['scope'],
                'scope_id' => (int)$row['scope_id'],
                'path'     => BuckarooFee::XPATH_SALES_DISPLAY_BUCKAROO_FEE,
                'value'    => (string)$displayType,
            ]);
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
