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
