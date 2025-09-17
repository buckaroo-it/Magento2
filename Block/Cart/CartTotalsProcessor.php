<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Block\Cart;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Checkout\Model\Layout\AbstractTotalsProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CartTotalsProcessor extends AbstractTotalsProcessor implements LayoutProcessorInterface
{
    /**
     * @var Factory
     */
    protected $configProviderFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Factory $configProviderFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Factory $configProviderFactory
    ) {
        parent::__construct($scopeConfig);
    }

    /**
     * @inheritdoc
     */
    public function process($jsLayout)
    {
        /**
         * @var Account $configProvider
         */
        $configProvider = $this->configProviderFactory->get('account');
        $paymentFeeLabel = $configProvider->getPaymentFeeLabel();

        $jsLayout['components']['block-totals']['children']['before_grandtotal']['children']['buckaroo-fee-order-level']
        ['config']['title'] = "Payment Fee";
        return $jsLayout;
    }
}
