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

namespace Buckaroo\Magento2\Model\SecondChance;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class EnabledStoresProvider
{
    /**
     * @var SecondChanceConfig
     */
    private $configProvider;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @param SecondChanceConfig       $configProvider
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        SecondChanceConfig $configProvider,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->configProvider = $configProvider;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Return enabled storefronts, excluding the admin store.
     *
     * @return StoreInterface[]
     */
    public function getEnabledStores(): array
    {
        $enabledStores = [];

        foreach ($this->storeRepository->getList() as $store) {
            if ((int) $store->getId() === 0) {
                continue;
            }

            if ($this->configProvider->isSecondChanceEnabled($store)) {
                $enabledStores[] = $store;
            }
        }

        return $enabledStores;
    }
}
