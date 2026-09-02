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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;

class CompositeConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ConfigProviderInterface[]
     */
    private $configProviders;

    /**
     * @param ConfigProviderInterface[] $configProviders
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        array $configProviders
    ) {
        $this->configProviders = $configProviders;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $config = [];
        foreach ($this->configProviders as $configProvider) {
            $config = array_merge_recursive($config, $configProvider->getConfig());
        }
        return $config;
    }
}
