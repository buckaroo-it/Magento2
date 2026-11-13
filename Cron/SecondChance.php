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
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Buckaroo\Magento2\Model\SecondChance\EnabledStoresProvider;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Magento\Store\Api\Data\StoreInterface;

class SecondChance
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
     * Process second chance emails for all enabled stores.
     *
     * @return $this
     */
    public function execute()
    {
        try {
            $stores = $this->enabledStoresProvider->getEnabledStores();
            if (empty($stores) || !$this->workChecker->hasProcessableItems($stores)) {
                return $this;
            }

            foreach ($stores as $store) {
                $this->processStore($store);
            }
        } catch (\Exception $e) {
            $this->logging->addError(__METHOD__ . '|SecondChance cron execution failed: ' . $e->getMessage());
            $this->logging->addError(__METHOD__ . '|Error file: ' . $e->getFile() . ':' . $e->getLine());
        }

        return $this;
    }

    /**
     * Process second chance email steps for a single store.
     *
     * @param StoreInterface $store
     * @return void
     */
    private function processStore(StoreInterface $store): void
    {
        foreach ([Collection::STEP_SECOND_EMAIL, Collection::STEP_FIRST_EMAIL] as $step) {
            try {
                $this->secondChanceRepository->getSecondChanceCollection($step, $store);
            } catch (\Exception $e) {
                $this->logging->addError(
                    __METHOD__ . '|Error processing step ' . $step
                    . ' for store ' . $store->getId()
                    . ': ' . $e->getMessage()
                );
            }
        }
    }
}
