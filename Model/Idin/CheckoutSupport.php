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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Idin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;

/**
 * Tells whether the checkout of a store has a step where the shopper can verify with iDIN.
 *
 * The default Magento checkout has this step. The Hyvä Checkout has it from Buckaroo Hyvä Checkout
 * module 2.4.0. The Hyvä React Checkout does not have it.
 */
class CheckoutSupport
{
    public const HYVA_CHECKOUT = 'Hyvä Checkout';
    public const HYVA_REACT_CHECKOUT = 'Hyvä React Checkout';

    /**
     * Checkout component of the iDIN step in the Buckaroo Hyvä Checkout module
     *
     * A string, not ::class, because that module is optional and the class may not exist.
     */
    // phpcs:ignore Magento2.PHP.LiteralNamespaces.LiteralClassUsage
    public const HYVA_IDIN_STEP_CLASS = 'Buckaroo\HyvaCheckout\Magewire\Checkout\Idin';

    private const XML_PATH_HYVA_CHECKOUT = 'hyva_themes_checkout/general/checkout';
    private const XML_PATH_HYVA_MOBILE_ENABLE = 'hyva_themes_checkout/general/mobile/enable';
    private const XML_PATH_HYVA_MOBILE_CHECKOUT = 'hyva_themes_checkout/general/mobile/checkout';
    private const XML_PATH_HYVA_REACT_CHECKOUT_ENABLE = 'hyva_react_checkout/general/enable';

    /**
     * Hyvä Checkout setting value for the default Magento checkout
     */
    private const LUMA_CHECKOUT = 'magento_luma';

    private const HYVA_CHECKOUT_MODULE = 'Hyva_Checkout';
    private const BUCKAROO_HYVA_CHECKOUT_MODULE = 'Buckaroo_HyvaCheckout';

    /**
     * Module name of the Hyvä React Checkout, before and after its move to Friends of Hyvä
     */
    private const HYVA_REACT_CHECKOUT_MODULES = ['FriendsOfHyva_ReactCheckout', 'Hyva_ReactCheckout'];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var string
     */
    private $hyvaIdinStepClass;

    /**
     * @var bool|null
     */
    private $hasHyvaIdinStep;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleManager        $moduleManager
     * @param string               $hyvaIdinStepClass
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleManager $moduleManager,
        string $hyvaIdinStepClass = self::HYVA_IDIN_STEP_CLASS
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->hyvaIdinStepClass = $hyvaIdinStepClass;
    }

    /**
     * Name of the checkout the store uses when that checkout has no iDIN step, null when it has one
     *
     * @param int|string|null $storeId
     *
     * @return string|null
     */
    public function getCheckoutWithoutIdinStep($storeId = null): ?string
    {
        if ($this->isHyvaReactCheckoutActive($storeId)) {
            return self::HYVA_REACT_CHECKOUT;
        }

        if ($this->isHyvaCheckoutActive($storeId) && !$this->hasHyvaIdinStep()) {
            return self::HYVA_CHECKOUT;
        }

        return null;
    }

    /**
     * Whether the store shows the Hyvä React Checkout
     *
     * @param int|string|null $storeId
     *
     * @return bool
     */
    private function isHyvaReactCheckoutActive($storeId): bool
    {
        foreach (self::HYVA_REACT_CHECKOUT_MODULES as $module) {
            if ($this->moduleManager->isEnabled($module)) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_HYVA_REACT_CHECKOUT_ENABLE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            }
        }

        return false;
    }

    /**
     * Whether the store shows the Hyvä Checkout, on desktop or on mobile
     *
     * @param int|string|null $storeId
     *
     * @return bool
     */
    private function isHyvaCheckoutActive($storeId): bool
    {
        if (!$this->moduleManager->isEnabled(self::HYVA_CHECKOUT_MODULE)) {
            return false;
        }

        if ($this->isHyvaCheckout($this->getStoreValue(self::XML_PATH_HYVA_CHECKOUT, $storeId))) {
            return true;
        }

        return $this->scopeConfig->isSetFlag(self::XML_PATH_HYVA_MOBILE_ENABLE, ScopeInterface::SCOPE_STORE, $storeId)
            && $this->isHyvaCheckout($this->getStoreValue(self::XML_PATH_HYVA_MOBILE_CHECKOUT, $storeId));
    }

    /**
     * Whether a Hyvä Checkout setting value names a Hyvä checkout instead of the default Magento checkout
     *
     * @param mixed $checkout
     *
     * @return bool
     */
    private function isHyvaCheckout($checkout): bool
    {
        return is_string($checkout) && $checkout !== '' && $checkout !== self::LUMA_CHECKOUT;
    }

    /**
     * Whether the installed Buckaroo Hyvä Checkout module shows the iDIN step
     *
     * @return bool
     */
    private function hasHyvaIdinStep(): bool
    {
        if ($this->hasHyvaIdinStep === null) {
            $this->hasHyvaIdinStep = $this->moduleManager->isEnabled(self::BUCKAROO_HYVA_CHECKOUT_MODULE)
                && class_exists($this->hyvaIdinStepClass);
        }

        return $this->hasHyvaIdinStep;
    }

    /**
     * Read a store scope setting
     *
     * @param string          $path
     * @param int|string|null $storeId
     *
     * @return mixed
     */
    private function getStoreValue(string $path, $storeId)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
