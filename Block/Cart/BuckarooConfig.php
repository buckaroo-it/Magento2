<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Block\Cart;

class BuckarooConfig extends \Magento\Backend\Block\Template
{
    /**
     * @var bool
     */
    protected $_isScopePrivate = false;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Factory
     */
    protected $configProviderFactory;

    /**
     * @var \Magento\Framework\Json\Encoder
     */
    protected $jsonEncoder;

    /**
     * @param \Magento\Backend\Block\Template\Context    $context
     * @param \Magento\Framework\Json\Encoder            $jsonEncoder
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     * @param array                                      $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\Encoder $jsonEncoder,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->jsonEncoder = $jsonEncoder;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * Retrieve buckaroo configuration
     *
     * @return array
     */
    public function getBuckarooConfigJson()
    {
        $configProvider = $this->configProviderFactory->get('buckaroo_fee');
        return $this->jsonEncoder->encode($configProvider->getConfig());
    }
}
