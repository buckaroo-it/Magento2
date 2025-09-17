<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Api\Data\AnalyticsInterface;
use Buckaroo\Magento2\Api\AnalyticsRepositoryInterface;
use Buckaroo\Magento2\Model\Analytics\ConfigProvider\Analytics as AnalyticsConfigProvider;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Analytics implements ObserverInterface
{
    private CookieManagerInterface $cookieManager;
    private AnalyticsInterface $analyticsModel;
    private AnalyticsRepositoryInterface $analyticsRepository;
    private AnalyticsConfigProvider $configProvider;
    private BuckarooLog $log;

    public function __construct(
        CookieManagerInterface $cookieManager,
        AnalyticsInterface $analyticsModel,
        AnalyticsRepositoryInterface $analyticsRepository,
        AnalyticsConfigProvider $configProvider,
        BuckarooLog $log
    ) {
        $this->cookieManager = $cookieManager;
        $this->analyticsModel = $analyticsModel;
        $this->analyticsRepository = $analyticsRepository;
        $this->configProvider = $configProvider;
        $this->log = $log;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        //check if this feature is enabled
        if (!$this->configProvider->isClientIdTrackingEnabled()) {
            return;
        }

        try {
            $quote  = $observer->getEvent()->getQuote();
            if ($quote) {
                $quote_id = $quote->getEntityId();
            }
        } catch (\Exception $e) {
            $this->log->error($e);
        }

        if (isset($quote_id)) {
            $ga_cookie = $this->cookieManager->getCookie(
                '_ga'
            );
            $this->analyticsModel->setQuoteId($quote_id);
            $this->analyticsModel->setClientId($ga_cookie);
            
            try {
                $this->analyticsRepository->save($this->analyticsModel);
            } catch (\Exception $e) {
                $this->log->error($e);
            }
        }
    }
}
