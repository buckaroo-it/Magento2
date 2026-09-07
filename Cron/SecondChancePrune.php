<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
