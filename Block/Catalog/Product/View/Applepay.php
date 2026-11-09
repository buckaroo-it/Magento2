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

class Applepay extends Template implements ShortcutInterface
{
    /**
     * Alias used when this block is added as a shortcut button.
     */
    private const SHORTCUT_ALIAS = 'buckaroo_magento2.checkout.cart.applepay';

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
     * @param ApplepayConfig          $applepayConfigProvider
     * @param AccountConfig           $accountConfigProvider
     * @param Registry|null           $registry
     * @param array                   $data
     * @param IdinConfigProvider|null $idinConfigProvider
     */
    public function __construct(
        Context $context,
        Cart $cart,
        CompositeConfigProvider $compositeConfigProvider,
        ApplepayConfig $applepayConfigProvider,
        AccountConfig $accountConfigProvider,
        ?Registry $registry = null,
        array $data = [],
        ?IdinConfigProvider $idinConfigProvider = null
    ) {
        parent::__construct($context, $data);

        $this->cart = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->applepayConfigProvider = $applepayConfigProvider;
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
     * Check whether the Apple Pay button can be shown on the given page.
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
     * Retrieve current product model (returns null if not on product page)
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
