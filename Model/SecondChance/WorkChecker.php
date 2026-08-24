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

namespace Buckaroo\Magento2\Model\SecondChance;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Api\Data\StoreInterface;

class WorkChecker
{
    private const STEP_FIRST_EMAIL = 1;
    private const STEP_SECOND_EMAIL = 2;

    /**
     * @var RecordsQuery
     */
    private $recordsQuery;

    /**
     * @var SecondChanceConfig
     */
    private $configProvider;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param RecordsQuery       $recordsQuery
     * @param SecondChanceConfig $configProvider
     * @param DateTime           $dateTime
     */
    public function __construct(
        RecordsQuery $recordsQuery,
        SecondChanceConfig $configProvider,
        DateTime $dateTime
    ) {
        $this->recordsQuery = $recordsQuery;
        $this->configProvider = $configProvider;
        $this->dateTime = $dateTime;
    }

    /**
     * Check for records that may be due for either email step.
     *
     * Stores are grouped by their configured delay so every candidate query
     * uses the exact threshold for those stores.
     *
     * @param StoreInterface[] $stores
     * @return bool
     */
    public function hasProcessableItems(array $stores): bool
    {
        return $this->hasItemsForStep($stores, self::STEP_SECOND_EMAIL)
            || $this->hasItemsForStep($stores, self::STEP_FIRST_EMAIL);
    }

    /**
     * Check for records that may be old enough to prune.
     *
     * @param StoreInterface[] $stores
     * @return bool
     */
    public function hasPrunableItems(array $stores): bool
    {
        $storeIdsByDays = [];

        foreach ($stores as $store) {
            $days = $this->configProvider->getSecondChanceDeleteAfterDays($store);
            if ($days <= 0) {
                continue;
            }

            $storeIdsByDays[$days][] = (int) $store->getId();
        }

        ksort($storeIdsByDays, SORT_NUMERIC);

        foreach ($storeIdsByDays as $days => $storeIds) {
            if ($this->recordsQuery->hasRecords([
                'store_id' => ['in' => $storeIds],
                'created_at' => [
                    'lt' => $this->getCutoff($days * 86400),
                ],
            ])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for candidate records for a specific email step.
     *
     * @param StoreInterface[] $stores
     * @param int              $step
     * @return bool
     */
    private function hasItemsForStep(array $stores, int $step): bool
    {
        $storeIdsByDelay = [];

        foreach ($stores as $store) {
            if (!$this->isStepEnabled($step, $store)) {
                continue;
            }

            $delay = max(0, $this->configProvider->getSecondChanceDelay($step, $store));
            $storeIdsByDelay[$delay][] = (int) $store->getId();
        }

        $status = 'step1_sent';
        $dateField = 'first_email_sent';
        if ($step === self::STEP_FIRST_EMAIL) {
            $status = 'pending';
            $dateField = 'created_at';
        }

        ksort($storeIdsByDelay, SORT_NUMERIC);

        foreach ($storeIdsByDelay as $delay => $storeIds) {
            $operator = $delay === 0 ? 'lteq' : 'lt';
            if ($this->recordsQuery->hasRecords([
                'store_id' => ['in' => $storeIds],
                'status' => $status,
                $dateField => [
                    $operator => $this->getCutoff($delay * 3600),
                ],
            ])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a configured email step is enabled for a store.
     *
     * @param int            $step
     * @param StoreInterface $store
     * @return bool
     */
    private function isStepEnabled(int $step, StoreInterface $store): bool
    {
        if ($step === self::STEP_FIRST_EMAIL) {
            return $this->configProvider->isFirstEmailEnabled($store);
        }

        return $this->configProvider->isSecondEmailEnabled($store);
    }

    /**
     * Return a GMT cutoff relative to the current time.
     *
     * @param int $seconds
     * @return string
     */
    private function getCutoff(int $seconds): string
    {
        return $this->dateTime->gmtDate(
            null,
            $this->dateTime->gmtTimestamp() - $seconds
        );
    }
}
