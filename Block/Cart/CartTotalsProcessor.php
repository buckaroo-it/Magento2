<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Block\Cart;

use Magento\Checkout\Model\Layout\AbstractTotalsProcessor;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

class CartTotalsProcessor extends AbstractTotalsProcessor implements LayoutProcessorInterface
{
    /**
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory
     */
    protected $configProviderFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory         $configProviderFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
    ) {
        parent::__construct($scopeConfig);
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * @inheritdoc
     */
    public function process($jsLayout)
    {
        /**
        * @var \Buckaroo\Magento2\Model\ConfigProvider\Account $configProvider
        */
        $configProvider = $this->configProviderFactory->get('account');
        $paymentFeeLabel = $configProvider->getPaymentFeeLabel();

        $jsLayout['components']['block-totals']['children']['before_grandtotal']['children']['buckaroo-fee-order-level']
        ['config']['title'] = $paymentFeeLabel;
        return $jsLayout;
    }
}
