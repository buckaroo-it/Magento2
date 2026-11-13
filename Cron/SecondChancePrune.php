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
namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\SecondChance\EnabledStoresProvider;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Buckaroo\Magento2\Model\SecondChanceRepository;

class SecondChancePrune
{
    /**
     * @var EnabledStoresProvider
     */
    private $enabledStoresProvider;

    /**
     * @var WorkChecker
     */
    private $workChecker;

    /**
     * @var Log
     */
    protected $logging;

    /**
     * @var SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param EnabledStoresProvider  $enabledStoresProvider
     * @param WorkChecker            $workChecker
     * @param Log                    $logging
     * @param SecondChanceRepository $secondChanceRepository
     */
    public function __construct(
        EnabledStoresProvider $enabledStoresProvider,
        WorkChecker $workChecker,
        Log $logging,
        SecondChanceRepository $secondChanceRepository
    ) {
        $this->enabledStoresProvider  = $enabledStoresProvider;
        $this->workChecker            = $workChecker;
        $this->logging                = $logging;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    /**
     * Execute cron job to prune old SecondChance records.
     *
     * @return $this
     */
    public function execute()
    {
        try {
            $stores = $this->enabledStoresProvider->getEnabledStores();
            if (empty($stores) || !$this->workChecker->hasPrunableItems($stores)) {
                return $this;
            }

            foreach ($stores as $store) {
                try {
                    $deletedRecords = $this->secondChanceRepository->deleteOlderRecords($store);
                    if ($deletedRecords > 0) {
                        $this->logging->addDebug(
                            __METHOD__ . '|Pruned ' . $deletedRecords . ' old records for store: ' . $store->getId()
                        );
                    }
                } catch (\Exception $e) {
                    $this->logging->addError(
                        'Error pruning SecondChance records for store ' . $store->getId() . ': ' . $e->getMessage()
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logging->addError(
                __METHOD__ . '|SecondChance prune cron execution failed: ' . $e->getMessage()
            );
        }

        return $this;
    }
}
