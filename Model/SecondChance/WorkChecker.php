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

namespace Buckaroo\Magento2\Model\SecondChance;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Answers whether the SecondChance crons have anything to do, so an idle run can exit
 * before it touches any store.
 *
 * The candidate query itself is owned by the collection, which is also what the processing
 * path uses. This class only decides which stores to ask about and groups them so a shared
 * timing window costs a single count query instead of one per store.
 */
class WorkChecker
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SecondChanceConfig
     */
    private $configProvider;

    /**
     * @param CollectionFactory  $collectionFactory
     * @param SecondChanceConfig $configProvider
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        SecondChanceConfig $configProvider
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->configProvider = $configProvider;
    }

    /**
     * Check whether any store has a record that is due for one of the email steps.
     *
     * @param StoreInterface[] $stores
     * @return bool
     */
    public function hasProcessableItems(array $stores): bool
    {
        return $this->hasItemsForStep($stores, Collection::STEP_SECOND_EMAIL)
            || $this->hasItemsForStep($stores, Collection::STEP_FIRST_EMAIL);
    }

    /**
     * Check whether any store has records that fall outside its retention window.
     *
     * @param StoreInterface[] $stores
     * @return bool
     */
    public function hasPrunableItems(array $stores): bool
    {
        $storeIdsByDays = $this->groupStoreIds(
            $stores,
            function (StoreInterface $store) {
                return $this->configProvider->isRecordPruningEnabled($store)
                    ? $this->configProvider->getSecondChanceDeleteAfterDays($store)
                    : null;
            }
        );

        foreach ($storeIdsByDays as $days => $storeIds) {
            $reminderWindowHours = $this->getLongestReminderWindow($stores, $storeIds);
            $collection = $this->collectionFactory->create()
                ->addStoreFilter($storeIds)
                ->addRemovableFilter((int) $days, $reminderWindowHours);

            if ($collection->getSize() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether any store has a record that is due for a specific email step.
     *
     * @param StoreInterface[] $stores
     * @param int              $step
     * @return bool
     */
    private function hasItemsForStep(array $stores, int $step): bool
    {
        $storeIdsByDelay = $this->groupStoreIds(
            $stores,
            function (StoreInterface $store) use ($step) {
                return $this->configProvider->isEmailStepEnabled($step, $store)
                    ? $this->configProvider->getSecondChanceDelay($step, $store)
                    : null;
            }
        );

        foreach ($storeIdsByDelay as $delay => $storeIds) {
            $collection = $this->collectionFactory->create()
                ->addStoreFilter($storeIds)
                ->addStepDueFilter($step, (int) $delay);

            if ($collection->getSize() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the longest reminder window among the given stores.
     *
     * The gate counts several stores in one query, so it has to use the most protective
     * window of the group. A store whose window is shorter is simply counted conservatively.
     *
     * @param StoreInterface[] $stores
     * @param int[]            $storeIds
     * @return int Hours
     */
    private function getLongestReminderWindow(array $stores, array $storeIds): int
    {
        $window = 0;

        foreach ($stores as $store) {
            if (!in_array((int) $store->getId(), $storeIds, true)) {
                continue;
            }

            $window = max($window, $this->configProvider->getReminderWindowHours($store));
        }

        return $window;
    }

    /**
     * Group store ids by the timing window they share, skipping stores the resolver rejects.
     *
     * @param StoreInterface[] $stores
     * @param callable         $windowResolver Returns the window for a store, or null to skip it
     * @return array<int, int[]>
     */
    private function groupStoreIds(array $stores, callable $windowResolver): array
    {
        $grouped = [];

        foreach ($stores as $store) {
            $window = $windowResolver($store);
            if ($window === null) {
                continue;
            }

            $grouped[$window][] = (int) $store->getId();
        }

        ksort($grouped, SORT_NUMERIC);

        return $grouped;
    }
}
