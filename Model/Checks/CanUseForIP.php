<?php

namespace Buckaroo\Magento2\Model\Checks;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Developer\Helper\Data;
use Magento\Payment\Model\Checks\SpecificationInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class CanUseForIP implements SpecificationInterface
{
    /**
     * @var Factory
     */
    public Factory $configProviderFactory;

    /**
     * @var Data
     */
    protected Data $developmentHelper;

    public function __construct(Factory $configProviderFactory, Data $developmentHelper)
    {
        $this->configProviderFactory = $configProviderFactory;
        $this->developmentHelper = $developmentHelper;
    }


    /**
     * @throws Exception
     */
    public function isApplicable(MethodInterface $paymentMethod, Quote $quote)
    {
        /**
         * @var Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');
        if ($accountConfig->getActive() == 0) {
            return false;
        }

        if (!$this->isAvailableBasedOnIp($paymentMethod, $accountConfig, $quote)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param MethodInterface $paymentMethod
     * @param Account $accountConfig
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnIp(
        MethodInterface $paymentMethod,
        Account $accountConfig,
        CartInterface $quote = null
    ): bool {
        $methodValue = $paymentMethod->getConfigData('limit_by_ip');
        if ($accountConfig->getLimitByIp() == 1 || $methodValue == 1) {
            $storeId = $quote ? $quote->getStoreId() : null;
            $isAllowed = $this->developmentHelper->isDevAllowed($storeId);

            if (!$isAllowed) {
                return false;
            }
        }

        return true;
    }
}
