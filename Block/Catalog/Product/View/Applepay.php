<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Block\Catalog\Product\View;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay as ApplepayConfig;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Applepay extends Template
{
    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var CompositeConfigProvider
     */
    private $compositeConfigProvider;

    /**
     * @var ApplepayConfig
     */
    private $applepayConfigProvider;

    /**
     * @var AccountConfig
     */
    private $accountConfigProvider;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Product
     */
    private $product;

    /**
     * @param Context                 $context
     * @param Cart                    $cart
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param ApplepayConfig          $applepayConfigProvider
     * @param AccountConfig           $accountConfigProvider
     * @param Registry|null           $registry
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        Cart $cart,
        CompositeConfigProvider $compositeConfigProvider,
        ApplepayConfig $applepayConfigProvider,
        AccountConfig $accountConfigProvider,
        ?Registry $registry = null,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->cart = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->applepayConfigProvider = $applepayConfigProvider;
        $this->accountConfigProvider = $accountConfigProvider;
        $this->registry = $registry;
    }

    /**
     * @param $page
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function canShowButton($page): bool
    {
        if (!$this->isModuleActive()) {
            return false;
        }

        $availableButtons = $this->applepayConfigProvider->getAvailableButtons();
        if (!in_array($page, $availableButtons, true)) {
            return false;
        }

        if (!$this->applepayConfigProvider->isApplePayEnabled()) {
            return false;
        }

        if (in_array($page, ['Product', 'Cart'], true)) {
            $integrationMode = $this->applepayConfigProvider->getIntegrationMode();
            if ($integrationMode === '1' || $integrationMode === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if Buckaroo module is active
     *
     * @return bool
     */
    public function isModuleActive()
    {
        $status = $this->applepayConfigProvider->getActive();
        return $status == 1 || $status == 2;
    }

    /**
     * Get checkout config
     *
     * @return false|string
     */
    public function getCheckoutConfig()
    {
        return json_encode($this->compositeConfigProvider->getConfig(), JSON_HEX_TAG);
    }

    /**
     * Get Apple Pay-specific config as JSON.
     *
     * @return string
     */
    public function getApplepayConfig()
    {
        return json_encode($this->applepayConfigProvider->getConfig());
    }

    /**
     * Check if iDIN verification is required for this context (product page or other)
     *
     * @return bool
     */
    public function isIdinVerificationRequired()
    {
        $idinMode = (int)$this->accountConfigProvider->getIdinMode();

        // Check if iDIN is enabled
        if (!$this->accountConfigProvider->getIdin() || $idinMode === null) {
            return false;
        }

        if ($idinMode === 0) {
            // Global mode - required for all products/contexts
            return true;
        } elseif ($idinMode === 1) {
            // Per Product mode - check if we're on product page and if product requires iDIN
            return $this->isProductPageWithIdinRequired();
        } elseif ($idinMode === 2) {
            // Per Category mode - check if current product(s) are in selected categories
            return $this->isInIdinRequiredCategory();
        }

        return false;
    }

    /**
     * Check if current product page requires iDIN (Per Product mode)
     *
     * @return bool
     */
    private function isProductPageWithIdinRequired()
    {
        $product = $this->getProduct();
        if (!$product) {
            return false; // Not a product page
        }
        $customAttribute = $product->getCustomAttribute('buckaroo_product_idin');
        return $customAttribute !== null && $customAttribute->getValue() == 1;
    }

    /**
     * Check if products are in iDIN required category (Per Category mode)
     *
     * @return bool
     */
    private function isInIdinRequiredCategory()
    {
        $product = $this->getProduct();
        if (!$product) {
            return false;
        }

        $idinCategories = explode(',', (string)$this->accountConfigProvider->getIdinCategory());
        foreach ($product->getCategoryIds() as $category) {
            if (in_array($category, $idinCategories)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve current product model (returns null if not on product page)
     *
     * @return Product|null
     */
    private function getProduct()
    {
        if ($this->product === null && $this->registry) {
            $this->product = $this->registry->registry('product');
        }

        // Return null if no product or invalid product
        return ($this->product && $this->product->getId()) ? $this->product : null;
    }
}
