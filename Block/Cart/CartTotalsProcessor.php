<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Block\Cart;

use Magento\Checkout\Model\Layout\AbstractTotalsProcessor;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CartTotalsProcessor extends AbstractTotalsProcessor implements LayoutProcessorInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($scopeConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function process($jsLayout)
    {
        $jsLayout['components']['block-totals']['children']['before_grandtotal']['children']['buckaroo-fee-order-level']
        ['config']['title'] = "Payment Fee";
        return $jsLayout;
    }
}
