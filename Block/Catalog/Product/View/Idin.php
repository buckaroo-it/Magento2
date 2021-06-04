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
namespace Buckaroo\Magento2\Block\Catalog\Product\View;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use \Magento\Framework\Registry;

class Idin extends Template
{
    /** @var Cart */
    private $cart;

    /** @var CompositeConfigProvider */
    private $compositeConfigProvider;

    /** @var AccountConfig */
    private $idinConfigProvider;

    /** @var Registry */
    protected $registry;

    /** * @var Product */
    private $product;

    /**
     * @param Context                 $context
     * @param Cart                    $cart
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param AccountConfig          $idinConfigProvider
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

        $this->registry                = $registry;
        $this->cart                    = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->idinConfigProvider      = $idinConfigProvider;
    }

    /**
     * @return Product
     */
    private function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');

            if (!$this->product->getId()) {
                throw new LocalizedException(__('Failed to initialize product'));
            }
        }

        return $this->product;
    }

    public function getProductName()
    {
        return $this->getProduct()->getName();
    }

    /**
     * @return bool
     */
    public function canShowProductIdin()
    {
        $result = false;
        if ($this->idinConfigProvider->getIdin() != 0) {
            switch ($this->idinConfigProvider->getIdinMode()) {
                case 1:
                    if (null !== $this->getProduct()->getCustomAttribute('buckaroo_product_idin')) {
                        $result = $this->getProduct()->getCustomAttribute('buckaroo_product_idin')->getValue() == 1 ? true : false;
                    }
                    break;
                case 2:
                    foreach ($this->getProduct()->getCategoryIds() as $key => $cat) {
                        if (in_array($cat, explode(',', $this->idinConfigProvider->getIdinCategory()))) {
                            $result = true;
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    /**
     * @return false|string
     */
    public function getAccountConfig()
    {
        return json_encode($this->idinConfigProvider->getConfig());
    }
}
