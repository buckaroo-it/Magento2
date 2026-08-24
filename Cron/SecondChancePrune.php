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
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Magento\Store\Api\StoreRepositoryInterface;

class SecondChancePrune
{
    /**
     * @var SecondChanceConfig
     */
    protected $configProvider;

    /**
     * @var Log
     */
    protected $logging;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param SecondChanceConfig       $configProvider
     * @param StoreRepositoryInterface $storeRepository
     * @param Log                      $logging
     * @param SecondChanceRepository   $secondChanceRepository
     */
    public function __construct(
        SecondChanceConfig $configProvider,
        StoreRepositoryInterface $storeRepository,
        Log $logging,
        SecondChanceRepository $secondChanceRepository
    ) {
        $this->configProvider         = $configProvider;
        $this->storeRepository        = $storeRepository;
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
        $stores = $this->storeRepository->getList();

        $anyEnabled = false;
        foreach ($stores as $store) {
            if ($store->getId() === 0) {
                continue;
            }
            if ($this->configProvider->isSecondChanceEnabled($store)) {
                $anyEnabled = true;
                break;
            }
        }

        if (!$anyEnabled) {
            return $this;
        }

        foreach ($stores as $store) {
            if ($store->getId() === 0) {
                continue;
            }

            if ($this->configProvider->isSecondChanceEnabled($store)) {
                $this->logging->addDebug(__METHOD__ . '|Pruning old records for store: ' . $store->getId());

                try {
                    $this->secondChanceRepository->deleteOlderRecords($store);
                } catch (\Exception $e) {
                    $this->logging->addError('Error pruning SecondChance records for store ' . $store->getId() . ': ' . $e->getMessage());
                }
            }
        }

        return $this;
    }
}
