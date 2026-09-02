<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\States;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Order\Config;

class StatusesPending implements OptionSourceInterface
{
    /**
     * Core order config
     *
     * @var Config
     */
    protected $orderConfig;

    /**
     * @var Factory
     */
    protected $configProviderFactory;

    /**
     * Class constructor
     *
     * @param Config  $orderConfig
     * @param Factory $configProviderFactory
     */
    public function __construct(
        Config $orderConfig,
        Factory $configProviderFactory
    ) {
        $this->orderConfig = $orderConfig;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * Options getter
     *
     * @throws Exception
     *
     * @return array
     */
    public function toOptionArray()
    {
        /**
         * @var States $statesConfig
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
