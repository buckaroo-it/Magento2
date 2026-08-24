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
use Magento\Store\Api\Data\StoreInterface;

class SecondChance
{
    public const STEP_FIRST_EMAIL  = 1;
    public const STEP_SECOND_EMAIL = 2;

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
        foreach ([self::STEP_SECOND_EMAIL, self::STEP_FIRST_EMAIL] as $step) {
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
