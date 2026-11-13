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

namespace Buckaroo\Magento2\Block\Catalog\Product\View;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay as GooglepayConfig;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Googlepay extends Template implements ShortcutInterface
{
    /**
     * Alias used when this block is added as a shortcut button.
     */
    private const SHORTCUT_ALIAS = 'buckaroo_magento2.checkout.cart.googlepay';

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var CompositeConfigProvider
     */
    private $compositeConfigProvider;

    /**
     * @var GooglepayConfig
     */
    private $googlepayConfigProvider;

    /**
     * @var AccountConfig
     */
    private $accountConfigProvider;

    /**
     * @var IdinConfigProvider|null
     */
    private $idinConfigProvider;

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
     * @param GooglepayConfig         $googlepayConfigProvider
     * @param AccountConfig           $accountConfigProvider
     * @param Registry|null           $registry
     * @param array                   $data
     * @param IdinConfigProvider|null $idinConfigProvider
     */
    public function __construct(
        Context $context,
        Cart $cart,
        CompositeConfigProvider $compositeConfigProvider,
        GooglepayConfig $googlepayConfigProvider,
        AccountConfig $accountConfigProvider,
        ?Registry $registry = null,
        array $data = [],
        ?IdinConfigProvider $idinConfigProvider = null
    ) {
        parent::__construct($context, $data);

        $this->cart = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->googlepayConfigProvider = $googlepayConfigProvider;
        $this->accountConfigProvider = $accountConfigProvider;
        $this->registry = $registry;
        $this->idinConfigProvider = $idinConfigProvider;
    }

    /**
     * Get the shortcut alias used by the shortcut buttons container.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return self::SHORTCUT_ALIAS;
    }

    /**
     * Check if Google Pay is enabled for the specified page
     *
     * @param string $page
     * @throws NoSuchEntityException
     * @return bool
     */
    public function canShowButton($page): bool
    {
        if (!$this->isModuleActive()) {
            return false;
        }

        $availableButtons = $this->googlepayConfigProvider->getAvailableButtons();
        if (!in_array($page, $availableButtons, true)) {
            return false;
        }

        if (!$this->googlepayConfigProvider->isGooglepayEnabled()) {
            return false;
        }

        if (in_array($page, ['Product', 'Cart'], true)) {
            $integrationMode = $this->googlepayConfigProvider->getIntegrationMode();
            if ($integrationMode === '1' || $integrationMode === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the Buckaroo module is active
     *
     * @return bool
     */
    public function isModuleActive()
    {
        $status = $this->googlepayConfigProvider->getActive();
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
     * Get Google Pay-specific config as JSON.
     *
     * @return string
     */
    public function getGooglepayConfig()
    {
        return json_encode($this->googlepayConfigProvider->getConfig());
    }

    /**
     * Check if iDIN verification is still required for this context (product page or cart)
     *
     * @return bool
     */
    public function isIdinVerificationRequired()
    {
        if ($this->idinConfigProvider === null) {
            $this->idinConfigProvider = ObjectManager::getInstance()->get(IdinConfigProvider::class);
        }

        return $this->idinConfigProvider->isVerificationRequiredForExpress($this->getProduct());
    }

    /**
     * Retrieve the current product model (returns null if not on the product page)
     *
     * @return Product|null
     */
    public function getProduct()
    {
        if ($this->product === null && $this->registry) {
            $this->product = $this->registry->registry('product');
        }

        // Return null if no product or invalid product
        return ($this->product && $this->product->getId()) ? $this->product : null;
    }
}
