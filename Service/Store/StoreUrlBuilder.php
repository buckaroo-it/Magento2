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

namespace Buckaroo\Magento2\Service\Store;

use Buckaroo\Magento2\Helper\StoreId;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a URL against a specific store's own base URL.
 *
 * Every URL we hand to the gateway has to resolve to the host the order belongs to. On a setup
 * where each store view or website has its own domain, the ambient store is not good enough:
 *
 * - the push signature is rebuilt in `PushSDK` during the push, outside the shopper's request;
 * - `returnURL` is built during `placeOrder`, which for a PayPerEmail or PayLink order created in
 *   the admin runs with the admin's ambient store, not the order's.
 *
 * `UrlInterface::getDirectUrl()` cannot do this. Passing `['_scope' => $storeId]` does not change
 * the host it resolves - only `setScope()` or asking the store for its own base URL does. Verified
 * in this codebase's environment: in the adminhtml area with store 1 ambient,
 * `getDirectUrl($p, ['_scope' => 2])` still returns store 1's host.
 *
 * Asking the store for its base URL also still honours `web/url/use_store`, which puts the store
 * code in the base path.
 */
class StoreUrlBuilder
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param UrlInterface          $urlBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(UrlInterface $urlBuilder, StoreManagerInterface $storeManager)
    {
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * Join a path onto the given store's own base URL
     *
     * Falls back to the ambient URL when the store cannot be resolved, which is what the module
     * emitted before store scoping was added - a failure here degrades to the previous behaviour
     * rather than to a broken URL.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     * @param string                                                 $path
     *
     * @return string
     */
    public function getUrl($store, string $path): string
    {
        return $this->buildForStore($this->resolveStore($store), $path);
    }

    /**
     * Join a path onto the base URL of an already-resolved store
     *
     * @param StoreInterface|null $store
     * @param string              $path
     *
     * @return string
     */
    public function buildForStore(?StoreInterface $store, string $path): string
    {
        // getBaseUrl() lives on the concrete Store model, not on StoreInterface. StoreManager
        // always hands back the concrete one; fall back to the ambient URL if that ever changes.
        if (!$store instanceof Store) {
            return $this->urlBuilder->getDirectUrl($path);
        }

        return rtrim((string)$store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/') . '/' . $path;
    }

    /**
     * Resolve the store a URL has to be built for
     *
     * Returns null when it cannot be resolved, which callers read as "use the ambient URL".
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     *
     * @return StoreInterface|null
     */
    public function resolveStore($store): ?StoreInterface
    {
        $storeId = StoreId::normalize($store);

        try {
            return $storeId === null
                ? $this->storeManager->getStore()
                : $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }
}
