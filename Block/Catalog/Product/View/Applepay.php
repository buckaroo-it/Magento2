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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay as ApplepayConfig;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @param Context $context
     * @param Cart $cart
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param ApplepayConfig $applepayConfigProvider
     * @param array $data
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
     * @param $page
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

        return $this->applepayConfigProvider->isApplePayEnabled();
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
}
