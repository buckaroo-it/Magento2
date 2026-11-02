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

namespace Buckaroo\Magento2\Service\Config;

use Buckaroo\Magento2\Helper\StoreId;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The configuration scope the admin is editing, taken from the scope switcher
 */
class AdminConfigScope
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param RequestInterface      $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(RequestInterface $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    /**
     * Scope type and id of the edited scope: stores/<id>, websites/<id> or default/0
     *
     * @return array{0: string, 1: int}
     */
    public function getScope(): array
    {
        $storeId = StoreId::normalize($this->request->getParam('store'));
        if ($storeId !== null) {
            return [ScopeInterface::SCOPE_STORES, $storeId];
        }

        $websiteId = StoreId::normalize($this->request->getParam('website'));
        if ($websiteId !== null) {
            return [ScopeInterface::SCOPE_WEBSITES, $websiteId];
        }

        return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0];
    }

    /**
     * A store view standing for the edited scope (a website's default store view; null at default)
     *
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        [$scopeType, $scopeId] = $this->getScope();

        if ($scopeType === ScopeInterface::SCOPE_STORES) {
            return $scopeId;
        }

        if ($scopeType !== ScopeInterface::SCOPE_WEBSITES) {
            return null;
        }

        try {
            $defaultStore = $this->storeManager->getWebsite($scopeId)->getDefaultStore();
        } catch (\Exception $exception) {
            return null;
        }

        return $defaultStore !== null ? (int)$defaultStore->getId() : null;
    }
}
