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
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param \Buckaroo\Magento2\Logging\Log $logging
     * @param \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Buckaroo\Magento2\Logging\Log $logging,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
    ) {
        $this->configProvider         = $configProvider;
        $this->storeRepository        = $storeRepository;
        $this->logging                = $logging;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    public function execute()
    {
        $this->logging->addDebug(__METHOD__ . '|Starting SecondChance cron execution at ' . date('Y-m-d H:i:s'));
        
        try {
            $stores = $this->storeRepository->getList();
            $this->logging->addDebug(__METHOD__ . '|Found ' . count($stores) . ' stores to check');
            
            foreach ($stores as $store) {
                if ($store->getId() == 0) {
                    continue; // Skip admin store
                }
                
                $this->logging->addDebug(__METHOD__ . '|Checking store: ' . $store->getId() . ' (' . $store->getName() . ')');
                
                if ($this->configProvider->isSecondChanceEnabled($store)) {
                    $this->logging->addDebug(__METHOD__ . '|SecondChance is ENABLED for store: ' . $store->getId());
                    
                    // Log configuration details
                    $firstEmailEnabled = $this->configProvider->isFirstEmailEnabled($store);
                    $secondEmailEnabled = $this->configProvider->isSecondEmailEnabled($store);
                    $firstTiming = $this->configProvider->getFirstEmailTiming($store);
                    $secondTiming = $this->configProvider->getSecondEmailTiming($store);
                    
                    $this->logging->addDebug(__METHOD__ . '|Store config - First email: ' . ($firstEmailEnabled ? 'Yes' : 'No') . ' (' . $firstTiming . 'h), Second email: ' . ($secondEmailEnabled ? 'Yes' : 'No') . ' (' . $secondTiming . 'h)');
                    
                    // Process step 2 first (second email), then step 1 (first email)
                    foreach ([2, 1] as $step) {
                        $this->logging->addDebug(__METHOD__ . '|Processing step ' . $step . ' for store ' . $store->getId());
                        
                        try {
                            $this->secondChanceRepository->getSecondChanceCollection($step, $store);
                            $this->logging->addDebug(__METHOD__ . '|Completed processing step ' . $step . ' for store ' . $store->getId());
                        } catch (\Exception $e) {
                            $this->logging->addError(__METHOD__ . '|Error processing step ' . $step . ' for store ' . $store->getId() . ': ' . $e->getMessage());
                        }
                    }
                } else {
                    $this->logging->addDebug(__METHOD__ . '|SecondChance is DISABLED for store: ' . $store->getId());
                }
            }
            
            $this->logging->addDebug(__METHOD__ . '|SecondChance cron execution completed successfully at ' . date('Y-m-d H:i:s'));
            
        } catch (\Exception $e) {
            $this->logging->addError(__METHOD__ . '|SecondChance cron execution failed: ' . $e->getMessage());
            $this->logging->addError(__METHOD__ . '|Error file: ' . $e->getFile() . ':' . $e->getLine());
        }
        
        return $this;
    }
} 