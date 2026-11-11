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
 * Give the Bank Transfer payment instruction email its own setting.
 *
 * Both "Send Payment Instruction Email" and "Send Order Confirmation Email" used to be stored in
 * payment/buckaroo_magento2_transfer/order_email, so one always overwrote the other on save.
 * The payment instruction email now has its own path. This patch copies the stored order_email
 * value of every scope to that path, so each shop keeps sending exactly the emails it sends today.
 */
class MigrateTransferPaymentInstructionEmail implements DataPatchInterface
{
    public const OLD_PATH = 'payment/buckaroo_magento2_transfer/order_email';
    public const NEW_PATH = 'payment/buckaroo_magento2_transfer/payment_instruction_email';

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

        $currentRows = $connection->fetchAll(
            $connection->select()
                ->from($configTable, ['scope', 'scope_id', 'value'])
                ->where('path = ?', self::OLD_PATH)
        );

        $alreadySet = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($configTable, ['scope', 'scope_id'])
                ->where('path = ?', self::NEW_PATH)
        ) as $row) {
            $alreadySet[$row['scope'] . '/' . (int)$row['scope_id']] = true;
        }

        $newRows = [];
        foreach ($currentRows as $row) {
            if (isset($alreadySet[$row['scope'] . '/' . (int)$row['scope_id']])) {
                continue;
            }
            $newRows[] = [
                'scope'    => $row['scope'],
                'scope_id' => (int)$row['scope_id'],
                'path'     => self::NEW_PATH,
                'value'    => $row['value'],
            ];
        }

        if ($newRows) {
            $connection->insertMultiple($configTable, $newRows);
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
