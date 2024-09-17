<?php

namespace Buckaroo\Magento2\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;

class HyvaCheckoutConfig
{
    const HYVA_CHECKOUT_TYPE_PATH = 'hyva_themes_checkout/general/checkout';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * HyvaCheckoutConfig constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param LayoutInterface $layout
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LayoutInterface $layout
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->layout = $layout;
    }

    /**
     * Check if Hyvä checkout is enabled based on configuration and layout handles.
     *
     * @return bool
     */
    public function isHyvaCheckoutEnabled(): bool
    {
        return $this->isCheckoutConfiguredAsHyva() && $this->isHyvaLayoutActive();
    }

    /**
     * Check if the checkout type is set to Hyvä in the configuration.
     *
     * @return bool
     */
    private function isCheckoutConfiguredAsHyva(): bool
    {
        $checkoutType = $this->scopeConfig->getValue(
            self::HYVA_CHECKOUT_TYPE_PATH,
            ScopeInterface::SCOPE_STORE
        );

        // Adjust this condition based on how Hyvä checkout is configured in your setup
        return $checkoutType === 'default';
    }

    /**
     * Check if Hyvä-specific layout handles are active in the current layout.
     *
     * @return bool
     */
    private function isHyvaLayoutActive(): bool
    {
        // Check if known Hyvä layout handles are present in the current layout update
        return in_array('hyva_checkout_index_index', $this->layout->getUpdate()->getHandles());
    }
}
