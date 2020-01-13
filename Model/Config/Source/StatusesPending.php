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
namespace Buckaroo\Magento2\Model\Config\Source;

class StatusesPending implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Core order config
     *
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $orderConfig;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Factory
     */
    protected $configProviderFactory;

    /**
     * Class constructor
     *
     * @param \Magento\Sales\Model\Order\Config          $orderConfig
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     */
    public function __construct(
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
    ) {
        $this->orderConfig = $orderConfig;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\States $statesConfig
         */
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStatePending();

        $statuses = $this->orderConfig->getStateStatuses($state);

        $options = [];
        $options[] = ['value' => '', 'label' => __('-- Please Select --')];

        foreach ($statuses as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
