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
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Magento\Store\Api\StoreRepositoryInterface;

class SecondChance
{
    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\SecondChance
     */
    protected $configProvider;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider
     * @param StoreRepositoryInterface                             $storeRepository
     * @param Log                                                  $logging
     * @param SecondChanceRepository                               $secondChanceRepository
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider,
        StoreRepositoryInterface $storeRepository,
        Log $logging,
        SecondChanceRepository $secondChanceRepository
    ) {
        $this->configProvider         = $configProvider;
        $this->storeRepository        = $storeRepository;
        $this->logging                = $logging;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    public function execute()
    {
        try {
            $stores = $this->storeRepository->getList();

            foreach ($stores as $store) {
                if ($store->getId() == 0) {
                    continue; // Skip admin store
                }

                if ($this->configProvider->isSecondChanceEnabled($store)) {
                    // Process step 2 first (second email), then step 1 (first email)
                    foreach ([2, 1] as $step) {
                        try {
                            $this->secondChanceRepository->getSecondChanceCollection($step, $store);
                        } catch (\Exception $e) {
                            $this->logging->addError(__METHOD__ . '|Error processing step ' . $step . ' for store ' . $store->getId() . ': ' . $e->getMessage());
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logging->addError(__METHOD__ . '|SecondChance cron execution failed: ' . $e->getMessage());
            $this->logging->addError(__METHOD__ . '|Error file: ' . $e->getFile() . ':' . $e->getLine());
        }

        return $this;
    }
}
