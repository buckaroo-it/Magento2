<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace TIG\Buckaroo\Block\Cart;

use Magento\Checkout\Model\Layout\AbstractTotalsProcessor;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

class CartTotalsProcessor extends AbstractTotalsProcessor implements LayoutProcessorInterface
{
    /**
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory
     */
    protected $configProviderFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory         $configProviderFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory
    ) {
        parent::__construct($scopeConfig);
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function process($jsLayout)
    {
        /**
        * @var \TIG\Buckaroo\Model\ConfigProvider\Account $configProvider
        */
        $configProvider = $this->configProviderFactory->get('account');
        $paymentFeeLabel = $configProvider->getPaymentFeeLabel();

        $jsLayout['components']['block-totals']['children']['before_grandtotal']['children']['buckaroo-fee-order-level']
        ['config']['title'] = $paymentFeeLabel;
        return $jsLayout;
    }
}
