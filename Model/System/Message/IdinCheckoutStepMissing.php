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

namespace Buckaroo\Magento2\Model\System\Message;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Idin\CheckoutSupport;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Admin message for store views that have iDIN enabled while their checkout has no iDIN step
 */
class IdinCheckoutStepMissing implements MessageInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @var CheckoutSupport
     */
    private $checkoutSupport;

    /**
     * Store view id => [store view name, checkout name], filled on first use
     *
     * @var array<int, array{0: string, 1: string}>|null
     */
    private $storesWithoutIdinStep;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Account               $accountConfig
     * @param CheckoutSupport       $checkoutSupport
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Account $accountConfig,
        CheckoutSupport $checkoutSupport
    ) {
        $this->storeManager = $storeManager;
        $this->accountConfig = $accountConfig;
        $this->checkoutSupport = $checkoutSupport;
    }

    /**
     * @inheritdoc
     */
    public function getIdentity()
    {
        return 'buckaroo_idin_checkout_step_missing';
    }

    /**
     * @inheritdoc
     */
    public function isDisplayed()
    {
        return !empty($this->getStoresWithoutIdinStep());
    }

    /**
     * Message text, as plain text because the admin templates escape it
     *
     * @return string
     */
    public function getText()
    {
        $stores = [];
        $checkouts = [];
        foreach ($this->getStoresWithoutIdinStep() as [$storeName, $checkout]) {
            $stores[] = sprintf('%s (%s)', $storeName, $checkout);
            $checkouts[$checkout] = true;
        }

        $text = (string)__(
            'Buckaroo iDIN age verification is enabled, but the checkout of these store views has no iDIN step, so their orders are placed without iDIN verification: %1.',
            implode(', ', $stores)
        );
        if (isset($checkouts[CheckoutSupport::HYVA_CHECKOUT])) {
            $text .= ' ' . __('For the Hyvä Checkout, update the Buckaroo Hyvä Checkout module to version 2.4.0 or higher.');
        }
        if (isset($checkouts[CheckoutSupport::HYVA_REACT_CHECKOUT])) {
            $text .= ' ' . __('The Hyvä React Checkout has no iDIN step, use the Hyvä Checkout or the Magento checkout instead.');
        }

        return $text;
    }

    /**
     * @inheritdoc
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * Active store views with iDIN enabled whose checkout has no iDIN step
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function getStoresWithoutIdinStep(): array
    {
        if ($this->storesWithoutIdinStep !== null) {
            return $this->storesWithoutIdinStep;
        }

        $this->storesWithoutIdinStep = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            if (!$store->getIsActive() || !$this->accountConfig->getIdin($storeId)) {
                continue;
            }

            $checkout = $this->checkoutSupport->getCheckoutWithoutIdinStep($storeId);
            if ($checkout !== null) {
                $this->storesWithoutIdinStep[$storeId] = [(string)$store->getName(), $checkout];
            }
        }

        return $this->storesWithoutIdinStep;
    }
}
