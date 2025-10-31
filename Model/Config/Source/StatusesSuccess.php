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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\States;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Order\Config;

class StatusesSuccess implements OptionSourceInterface
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
     * @return array
     */
    public function toOptionArray(): array
    {
        /**
         * @var States $statesConfig
         */
        $statesConfig = $this->configProviderFactory->get('states');
        $state = $statesConfig->getOrderStateSuccess();

        $statuses = $this->orderConfig->getStateStatuses($state);

        $options = [];
        $options[] = ['value' => '', 'label' => __('-- Please Select --')];

        foreach ($statuses as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
