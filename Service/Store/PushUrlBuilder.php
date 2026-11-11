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

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Builds the push URL handed to the gateway, and the URLs a push signature may be verified against.
 *
 * The push arrives as a server-to-server REST call with no cookie, so the only way it can land in
 * the store view the order was placed in is for the URL itself to carry the store code:
 * Magento\Webapi\Controller\PathProcessor only calls setCurrentStore() for rest/<storeCode>/V1/...
 * — the bare rest/V1/... form falls through unhandled and leaves the default store view in place.
 *
 * Signature verification has to stay in step with this. The signed URI is the URL the gateway
 * actually called, and orders placed before this change carry the old storeless URL at Buckaroo,
 * so getCandidateUris() returns both forms and the validator accepts either.
 */
class PushUrlBuilder
{
    /**
     * Path of the push endpoint, without the leading rest/<storeCode> segment.
     */
    public const PUSH_PATH = 'V1/buckaroo/push';

    /**
     * @var StoreUrlBuilder
     */
    private StoreUrlBuilder $storeUrlBuilder;

    /**
     * @param StoreUrlBuilder $storeUrlBuilder
     */
    public function __construct(StoreUrlBuilder $storeUrlBuilder)
    {
        $this->storeUrlBuilder = $storeUrlBuilder;
    }

    /**
     * Get the push URL to send to the gateway for the given store
     *
     * Falls back to the storeless URL when the store cannot be resolved, which is what the module
     * has always emitted, so a failure here degrades to the previous behaviour rather than to a
     * broken URL.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     *
     * @return string
     */
    public function getPushUrl($store = null): string
    {
        $resolved = $this->resolveStore($store);

        if ($resolved === null || $this->isUsableCode((string)$resolved->getCode()) === false) {
            return $this->getStorelessPushUrl($resolved);
        }

        return $this->buildUrl($resolved, 'rest/' . $resolved->getCode() . '/' . self::PUSH_PATH);
    }

    /**
     * Get every URL a push for the given store may legitimately have been signed against
     *
     * Ordered newest form first. The storeless entry is what keeps pushes for orders already at
     * the gateway verifying after this change.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     *
     * @return string[]
     */
    public function getCandidateUris($store = null): array
    {
        return array_values(array_unique([
            $this->getPushUrl($store),
            $this->getStorelessPushUrl($this->resolveStore($store)),
        ]));
    }

    /**
     * Get the push URL without a store code, the form emitted before store scoping was added
     *
     * @param StoreInterface|null $store
     *
     * @return string
     */
    private function getStorelessPushUrl(?StoreInterface $store): string
    {
        return $this->buildUrl($store, 'rest/' . self::PUSH_PATH);
    }

    /**
     * Join a path onto the base URL of the given store
     *
     * The base URL has to come from the store the order belongs to, not from the ambient one. On a
     * setup where each website has its own domain the push is delivered to that website's host, and
     * the signature covers the URL the gateway called - so a URI rebuilt against the default
     * store's host would not match and the push would be rejected. StoreUrlBuilder carries the
     * detail of why UrlInterface cannot do this.
     *
     * @param StoreInterface|null $store
     * @param string              $path
     *
     * @return string
     */
    private function buildUrl(?StoreInterface $store, string $path): string
    {
        return $this->storeUrlBuilder->buildForStore($store, $path);
    }

    /**
     * Resolve the store the URL has to be built for
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     *
     * @return StoreInterface|null
     */
    private function resolveStore($store): ?StoreInterface
    {
        return $this->storeUrlBuilder->resolveStore($store);
    }

    /**
     * Whether a store code may be put in the REST path
     *
     * The admin store is deliberately excluded: rest/all/V1/... maps to it, and a push processed
     * in the admin scope resolves configuration against store 0 rather than the shopper's store.
     *
     * @param string $code
     *
     * @return bool
     */
    private function isUsableCode(string $code): bool
    {
        return $code !== '' && $code !== Store::ADMIN_CODE;
    }
}
