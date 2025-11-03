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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Idin extends Template
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var CompositeConfigProvider
     */
    private $compositeConfigProvider;

    /**
     * @var AccountConfig
     */
    private $idinConfigProvider;

    /**
     * @var Product
     */
    private $product;

    /**
     * Idin constructor
     *
     * @param Context                 $context
     * @param Cart                    $cart
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param AccountConfig           $idinConfigProvider
     * @param Registry                $registry
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        Cart $cart,
        CompositeConfigProvider $compositeConfigProvider,
        AccountConfig $idinConfigProvider,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->registry = $registry;
        $this->cart = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->idinConfigProvider = $idinConfigProvider;
    }

    /**
     * Get product name
     *
     * @throws LocalizedException
     *
     * @return string
     */
    public function getProductName()
    {
        return $this->getProduct()->getName();
    }

    /**
     * Retrieve current product model
     *
     * @throws LocalizedException
     *
     * @return Product
     */
    private function getProduct()
    {
        if ($this->product === null) {
            $this->product = $this->registry->registry('product');

            if (!$this->product->getId()) {
                throw new LocalizedException(__('Failed to initialize product'));
            }
        }

        return $this->product;
    }

    /**
     * Show Idin Notification about the age
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    public function canShowProductIdin()
    {
        $idinMode = (int)$this->idinConfigProvider->getIdinMode();
        $result = false;

        // Check if iDIN is enabled (not disabled)
        if ($this->idinConfigProvider->getIdin() && $idinMode !== null) {
            $product = $this->getProduct();

            if ($idinMode === 0) {
                // Global mode - show for all products
                $result = true;
            } elseif ($idinMode === 1) {
                // Per Product mode - check product attribute
                $customAttribute = $product->getCustomAttribute('buckaroo_product_idin');
                $result = $customAttribute !== null && $customAttribute->getValue() == 1;
            } elseif ($idinMode === 2) {
                // Per Category mode - check if product is in selected categories
                $idinCategories = explode(',', (string)$this->idinConfigProvider->getIdinCategory());
                foreach ($product->getCategoryIds() as $category) {
                    if (in_array($category, $idinCategories)) {
                        $result = true;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get idin account config
     *
     * @throws Exception
     *
     * @return false|string
     */
    public function getAccountConfig()
    {
        return json_encode($this->idinConfigProvider->getConfig());
    }
}
