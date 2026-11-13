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

namespace Buckaroo\Magento2\Helper;

use Magento\Store\Api\Data\StoreInterface;

// phpcs:disable Magento2.Functions.StaticFunction -- pure stateless type normaliser, mirrors Gateway\Helper\SubjectReader
/**
 * Normalises the shapes a "store" can arrive in into a plain int store id.
 *
 * Two problems this solves:
 *
 * 1. OrderInterface::getStoreId() is annotated int|null but actually returns a string, because it
 *    comes straight off the sales_order row. Passing it through unchanged breaks strict comparison,
 *    in_array() without $strict, match(true) arms and int-keyed array lookups. That class of bug
 *    presents as "works for store 1, not store 2", which is precisely the failure mode we are
 *    trying to remove when resolving configuration scope.
 * 2. Our config providers accept $store as int|string|StoreInterface|null, so every caller holding
 *    a Store object rather than an id has been converting it by hand.
 *
 * Call this where a store first enters a scope-sensitive code path, then pass the int onwards.
 */
class StoreId
{
    /**
     * Normalise a store id or store object to an int store id
     *
     * Returns null when no usable store was given, which callers should read as "use the ambient
     * store". A non-numeric value normalises to null rather than to 0 on purpose: 0 is the admin
     * store, and silently targeting it would resolve configuration against the wrong scope.
     *
     * @param StoreInterface|int|string|null $store
     *
     * @return int|null
     */
    public static function normalize($store = null): ?int
    {
        if ($store === null) {
            return null;
        }

        if ($store instanceof StoreInterface) {
            return self::normalize($store->getId());
        }

        if (is_int($store)) {
            return $store;
        }

        if (is_string($store) && is_numeric(trim($store))) {
            return (int)trim($store);
        }

        return null;
    }
}
