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
use Magento\Framework\App\Area;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Runs a callback with a given store emulated as the frontend.
 *
 * Use this at an entry point that holds an order but runs outside the frontend — the push webapi
 * route, a cron job, an admin action — and is about to execute a body of code that reads
 * configuration, translations or emails without being handed an explicit store. Without emulation
 * that code resolves against the ambient store, which in those contexts is the default store view
 * of the default website rather than the order's store.
 *
 * This is the same boundary pattern Magento itself uses in Order\Email\Sender\*, Order\Pdf\* and
 * Payment\Helper\Data::getInfoBlockHtml(). It is a safety net for code we do not control (third
 * party observers and plugins), not a substitute for passing the store to a reader that accepts
 * one.
 */
class StoreEmulator
{
    /**
     * @var Emulation
     */
    private Emulation $appEmulation;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Emulation             $appEmulation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Emulation $appEmulation, StoreManagerInterface $storeManager)
    {
        $this->appEmulation = $appEmulation;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute $callback with $store emulated, restoring the previous environment afterward
     *
     * When no usable store is given - or the ambient store is already the target - the callback
     * still runs, just without emulation, so callers never have to branch on that themselves.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     * @param callable                                               $callback
     *
     * @throws \Throwable
     *
     * @return mixed Whatever $callback returns
     */
    public function emulate($store, callable $callback)
    {
        $storeId = StoreId::normalize($store);

        if ($storeId === null || $this->isAlreadyCurrent($storeId)) {
            return $callback();
        }

        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        try {
            return $callback();
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Whether the ambient store is already the one we would emulate
     *
     * Emulation re-initialises the store, locale, design and translations, so running it when it
     * would change nothing is pure cost and pure risk - and on a single-store install that is
     * every single push. Skipping keeps this a no-op there while leaving the multi-store behaviour
     * untouched. A store we cannot resolve is treated as "not current", so we still emulate.
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function isAlreadyCurrent(int $storeId): bool
    {
        try {
            return (int)$this->storeManager->getStore()->getId() === $storeId;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
