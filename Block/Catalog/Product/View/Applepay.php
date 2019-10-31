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
namespace TIG\Buckaroo\Block\Catalog\Product\View;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use TIG\Buckaroo\Model\ConfigProvider\Method\Applepay as ApplepayConfig;

class Applepay extends Template
{
    /** @var Cart */
    private $cart;

    /** @var CompositeConfigProvider */
    private $compositeConfigProvider;

    /** @var ApplepayConfig */
    private $applepayConfigProvider;

    /**
     * @param Context                 $context
     * @param Cart                    $cart
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param ApplepayConfig          $applepayConfigProvider
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        Cart $cart,
        CompositeConfigProvider $compositeConfigProvider,
        ApplepayConfig $applepayConfigProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->cart = $cart;
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->applepayConfigProvider = $applepayConfigProvider;
    }

    /**
     * @return bool
     */
    public function canShowButton()
    {
        $result = false;

        if ($this->cart->getSummaryQty() && $this->applepayConfigProvider->getActive() != 0) {
            $result = true;
        }

        return $result;
    }

    /**
     * @return false|string
     */
    public function getCheckoutConfig()
    {
        if (!$this->canShowButton()) {
            return null;
        }

        return json_encode($this->compositeConfigProvider->getConfig(), JSON_HEX_TAG);
    }

    /**
     * @return false|string
     */
    public function getApplepayConfig()
    {
        return json_encode($this->applepayConfigProvider->getConfig());
    }
}
