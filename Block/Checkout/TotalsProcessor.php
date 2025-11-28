<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Buckaroo\Magento2\Block\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Checkout\Model\Layout\AbstractTotalsProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;

class TotalsProcessor extends AbstractTotalsProcessor implements LayoutProcessorInterface
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
        $jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['totals']
        ['children']['before_grandtotal']['children']['buckaroo_fee']['config']['title'] = 'Payment Fee';
        return $jsLayout;
    }
}
