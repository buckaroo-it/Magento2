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

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay as ApplepayConfig;

class Applepay extends Template
{
    /** @var CompositeConfigProvider */
    private $compositeConfigProvider;

    /** @var ApplepayConfig */
    private $applepayConfigProvider;

    /**
     * @param Context                 $context
     * @param CompositeConfigProvider $compositeConfigProvider
     * @param ApplepayConfig          $applepayConfigProvider
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        CompositeConfigProvider $compositeConfigProvider,
        ApplepayConfig $applepayConfigProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->compositeConfigProvider = $compositeConfigProvider;
        $this->applepayConfigProvider = $applepayConfigProvider;
    }

    /**
     * @param $page
     * @return bool
     * @throws NoSuchEntityException
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

        return $this->applepayConfigProvider->isApplePayEnabled($this->_storeManager->getStore());
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
     * Get entire checkout configuration as JSON.
     * Wrap in a try/catch to avoid "No such entity with cartId" exceptions.
     *
     * @return string
     */
    public function getCheckoutConfig()
    {
        try {
            $config = $this->compositeConfigProvider->getConfig();
        } catch (NoSuchEntityException $e) {
            $config = [];
        }

        return json_encode($config, JSON_HEX_TAG);
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
