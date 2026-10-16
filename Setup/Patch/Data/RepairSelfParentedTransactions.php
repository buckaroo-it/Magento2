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
use Psr\Log\LoggerInterface;

/**
 * Clear the parent of Buckaroo payment transactions that point to themselves.
 *
 * See DetachSelfParentedTransaction; this repairs rows saved before it was installed.
 */
class RepairSelfParentedTransactions implements DataPatchInterface
{
    /**
     * Prefix every payment method this module registers carries.
     */
    private const METHOD_CODE_PREFIX = 'buckaroo_magento2_';

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface          $logger
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup, LoggerInterface $logger)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $transactionTable = $this->moduleDataSetup->getTable('sales_payment_transaction');
        $transactionIds = $connection->fetchCol(
            $connection->select()
                ->from(['t' => $transactionTable], ['transaction_id'])
                ->join(
                    ['p' => $this->moduleDataSetup->getTable('sales_order_payment')],
                    'p.entity_id = t.payment_id',
                    []
                )
                ->where('t.parent_id = t.transaction_id')
                ->where('p.method LIKE ?', self::METHOD_CODE_PREFIX . '%')
        );

        foreach (array_chunk($transactionIds, 500) as $chunk) {
            $connection->update(
                $transactionTable,
                ['parent_id' => null],
                ['transaction_id IN (?)' => $chunk, 'parent_id = transaction_id']
            );
        }

        if ($transactionIds !== []) {
            $this->logger->info(sprintf(
                '[BUCKAROO] RepairSelfParentedTransactions: cleared the self-referencing parent of %d'
                . ' payment transaction(s).',
                count($transactionIds)
            ));
        }

        $connection->endSetup();

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
