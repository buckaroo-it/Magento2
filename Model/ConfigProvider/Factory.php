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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Buckaroo\Magento2\Exception as BuckarooException;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

class Factory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    protected array $configProviders;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array $configProviders
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $configProviders = []
    ) {
        $this->objectManager = $objectManager;
        $this->configProviders = $configProviders;
    }

    /**
     * Retrieve proper transaction builder for the specified transaction type.
     *
     * @param string $providerType
     *
     * @return ConfigProviderInterface
     * @throws \LogicException|BuckarooException
     */
    public function get(string $providerType): ConfigProviderInterface
    {
        if (empty($this->configProviders)) {
            throw new \LogicException('ConfigProvider adapter is not set.');
        }

        $providerType = str_replace('buckaroo_magento2_', '', $providerType);

        foreach ($this->configProviders as $configProviderMetaData) {
            $configProviderType = $configProviderMetaData['type'];
            if ($configProviderType == $providerType) {
                $configProviderClass = $configProviderMetaData['model'];
                break;
            }
        }

        if (empty($configProviderClass)) {
            throw new BuckarooException(
                new Phrase(
                    'Unknown ConfigProvider type requested: %1.',
                    [$providerType]
                )
            );
        }

        return $this->objectManager->get($configProviderClass);
    }

    /**
     * Checks if a specific config provider is present in the config providers list
     *
     * @param string $providerType
     * @return bool
     * @throws \LogicException
     */
    public function has(string $providerType): bool
    {
        if (empty($this->configProviders)) {
            throw new \LogicException('ConfigProvider adapter is not set.');
        }

        foreach ($this->configProviders as $configProviderMetaData) {
            $configProviderType = $configProviderMetaData['type'];
            if ($configProviderType == $providerType) {
                return true;
            }
        }

        return false;
    }
}
