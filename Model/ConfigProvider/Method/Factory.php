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

namespace TIG\Buckaroo\Model\ConfigProvider\Method;

class Factory
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $configProviders;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array                                     $configProviders
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
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
     * @throws \LogicException|\TIG\Buckaroo\Exception
     */
    public function get($providerType)
    {
        if (empty($this->configProviders)) {
            throw new \LogicException('ConfigProvider adapter is not set.');
        }

        $providerType = str_replace('tig_buckaroo_', '', $providerType);

        foreach ($this->configProviders as $configProviderMetaData) {
            $configProviderType = $configProviderMetaData['type'];
            if ($configProviderType == $providerType) {
                $configProviderClass = $configProviderMetaData['model'];
                break;
            }
        }

        if (!isset($configProviderClass) || empty($configProviderClass)) {
            throw new \TIG\Buckaroo\Exception(
                new \Magento\Framework\Phrase(
                    'Unknown ConfigProvider type requested: %1.',
                    [$providerType]
                )
            );
        }

        $configProvider = $this->objectManager->get($configProviderClass);
        if (!$configProvider instanceof ConfigProviderInterface) {
            throw new \LogicException(
                'The ConfigProvider must implement "TIG\Buckaroo\Model\ConfigProvider\Method\ConfigProviderInterface".'
            );
        }
        return $configProvider;
    }

    /**
     * @param $providerType
     *
     * @return bool
     *
     * @throws \LogicException
     */
    public function has($providerType)
    {
        if (empty($this->configProviders)) {
            throw new \LogicException('ConfigProvider adapter is not set.');
        }

        $providerType = str_replace('tig_buckaroo_', '', $providerType);

        foreach ($this->configProviders as $configProviderMetaData) {
            $configProviderType = $configProviderMetaData['type'];
            if ($configProviderType == $providerType) {
                return true;
            }
        }

        return false;
    }
}
