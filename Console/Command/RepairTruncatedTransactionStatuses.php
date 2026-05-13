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

namespace Buckaroo\Magento2\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Repairs orders where buckaroo_received_transactions_statuses grew large enough to truncate
 * the sales_order_payment.additional_information TEXT column (65 535-byte MySQL limit),
 * making the order unloadable by Magento.
 *
 * Root cause: BTI-117 — fixed in the current plugin version.
 * This command is a one-time repair tool for already-broken orders.
 *
 * Usage:
 *   bin/magento buckaroo:repair-truncated-statuses (repair all affected orders)
 *   bin/magento buckaroo:repair-truncated-statuses --dry-run (audit only, no DB writes)
 *   bin/magento buckaroo:repair-truncated-statuses --order-id=000000042
 */
class RepairTruncatedTransactionStatuses extends Command
{
    private const STATUSES_KEY = 'buckaroo_received_transactions_statuses';
    private const TEXT_COLUMN_MAX = 65535;
    private const STATUSES_ENTRY_THRESHOLD = 10;
    private const OPT_DRY_RUN = 'dry-run';
    private const OPT_ORDER_ID = 'order-id';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('buckaroo:repair-truncated-statuses');
        $this->setDescription(
            'Repairs orders where buckaroo_received_transactions_statuses truncated additional_information (BTI-117)'
        );
        $this->addOption(
            self::OPT_DRY_RUN,
            null,
            InputOption::VALUE_NONE,
            'Show affected orders without making any database changes'
        );
        $this->addOption(
            self::OPT_ORDER_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'Repair a specific order by increment_id (e.g. 000000042)'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $orderId  = $input->getOption(self::OPT_ORDER_ID);

        if ($isDryRun) {
            $output->writeln('<comment>DRY RUN — no changes will be written to the database</comment>');
        }

        $rows = $this->fetchAffectedPayments($orderId);

        if (empty($rows)) {
            $output->writeln('<info>No affected orders found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d affected payment(s).</info>', count($rows)));

        $repaired = 0;
        $failed   = 0;

        foreach ($rows as $row) {
            $result = $this->processRow($row, $isDryRun, $output);
            $result ? $repaired++ : $failed++;
        }

        if (!$isDryRun) {
            $output->writeln(sprintf(
                '<info>Done. Repaired: %d | Failed: %d</info>',
                $repaired,
                $failed
            ));
        }

        return $failed > 0 ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    private function fetchAffectedPayments(?string $orderId): array
    {
        $connection   = $this->resourceConnection->getConnection();
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $orderTable   = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(['sop' => $paymentTable], ['entity_id', 'additional_information'])
            ->join(['so' => $orderTable], 'so.entity_id = sop.parent_id', ['increment_id']);

        if ($orderId !== null) {
            $select->where('so.increment_id = ?', $orderId);
        } else {
            $threshold = self::STATUSES_ENTRY_THRESHOLD;
            $max       = self::TEXT_COLUMN_MAX;
            $key       = self::STATUSES_KEY;
            $select->where(
                "LENGTH(sop.additional_information) = {$max}"
                . " OR JSON_VALID(sop.additional_information) = 0"
                . " OR (JSON_VALID(sop.additional_information) = 1"
                . "     AND JSON_LENGTH(JSON_EXTRACT(sop.additional_information, '$.{$key}')) > {$threshold})"
            );
        }

        return $connection->fetchAll($select);
    }

    private function processRow(array $row, bool $isDryRun, OutputInterface $output): bool
    {
        $entityId    = $row['entity_id'];
        $incrementId = $row['increment_id'];
        $raw         = $row['additional_information'];

        $output->write(sprintf('  Order %s (payment #%d): ', $incrementId, $entityId));

        $entriesBefore = substr_count($raw, '"491"') + substr_count($raw, '"490"')
            + substr_count($raw, '"492"') + substr_count($raw, '"690"');

        $fixed = $this->repair($raw);

        if ($fixed === null) {
            $output->writeln('<error>unable to repair — skipping</error>');
            return false;
        }

        if ($isDryRun) {
            $output->writeln(sprintf(
                '<comment>would trim ~%d status entries down to 1 (raw length: %d bytes)</comment>',
                $entriesBefore,
                strlen($raw)
            ));
            return true;
        }

        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName('sales_order_payment'),
            ['additional_information' => $fixed],
            ['entity_id = ?' => $entityId]
        );

        $output->writeln(sprintf(
            '<info>repaired (trimmed ~%d entries, new length: %d bytes)</info>',
            $entriesBefore,
            strlen($fixed)
        ));

        return true;
    }

    private function repair(string $raw): ?string
    {
        $decoded = json_decode($raw, true);

        if ($decoded !== null) {
            return $this->pruneValidJson($decoded);
        }

        return $this->repairTruncatedJson($raw);
    }

    /**
     * JSON is valid, but the statuses array is too large — keep only the last entry.
     * The duplicate-check in DefaultProcessor only compares the most recent status per txId,
     * so pruning to one entry is functionally equivalent.
     */
    private function pruneValidJson(array $decoded): string
    {
        if (!isset($decoded[self::STATUSES_KEY]) || !is_array($decoded[self::STATUSES_KEY])) {
            return json_encode($decoded);
        }

        $statuses = $decoded[self::STATUSES_KEY];
        $lastKey  = array_key_last($statuses);

        $decoded[self::STATUSES_KEY] = [$lastKey => $statuses[$lastKey]];

        return json_encode($decoded);
    }

    /**
     * JSON is truncated (MySQL TEXT column overflow) — perform string surgery.
     * We keep all keys that appear BEFORE buckaroo_received_transactions_statuses
     * and replace the entire (broken) statuses value with an empty object.
     * Keys that appeared AFTER the statuses key are unrecoverable and are lost.
     */
    private function repairTruncatedJson(string $raw): ?string
    {
        $needle = '"' . self::STATUSES_KEY . '":{';
        $pos    = strpos($raw, $needle);

        if ($pos === false) {
            return null;
        }

        $prefix = rtrim(substr($raw, 0, $pos), ',');
        $fixed  = $prefix . ',"' . self::STATUSES_KEY . '":{}}';

        if (json_decode($fixed) !== null) {
            return $fixed;
        }

        // Prefix was also malformed; fall back to a minimal valid object
        return '{"' . self::STATUSES_KEY . '":{}}';
    }
}
