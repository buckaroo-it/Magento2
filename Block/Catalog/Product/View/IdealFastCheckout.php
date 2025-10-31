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

namespace Buckaroo\Magento2\Block\Catalog\Product\View;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal;
use Magento\Catalog\Model\Product;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\View\Element\Template\Context;

class IdealFastCheckout extends Template
{
    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @var Ideal
     */
    protected $idealConfig;
    protected $ideal;
    protected $assetRepo;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Product
     */
    private $product;

    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Ideal $idealConfig,
        Repository $assetRepo,
        ?Registry $registry = null,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->idealConfig = $idealConfig;
        $this->assetRepo = $assetRepo;
        $this->registry = $registry;
    }

    /**
     * Determine if the product button can be shown
     *
     * @param mixed $page
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function canShowButton($page)
    {
        return ($this->isModuleActive() &&
            $this->idealConfig->isFastCheckoutEnabled($this->_storeManager->getStore()) &&
            $this->idealConfig->canShowButtonForPage($page, $this->_storeManager->getStore()) &&
            $this->idealConfig->isIDealEnabled());
    }

    /**
     * Check if Buckaroo module is active
     *
     * @return bool
     */
    public function isModuleActive()
    {
        $status = $this->configProviderAccount->getActive();
        return $status == 1 || $status == 2;
    }

    /**
     * Get logo based on chosen color setting
     *
     * @throws NoSuchEntityException
     *
     * @return mixed
     */
    public function getLogo()
    {

        $logoColor = $this->idealConfig->getLogoColor($this->_storeManager->getStore());

        if ($logoColor == "Light") {
            $name = "ideal/ideal-fast-checkout-rgb-light.png";
        } else {
            $name = "ideal/ideal-fast-checkout-rgb-dark.png";
        }
        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
    }

    /**
     * Get all required data
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'currency' => $this->getCurrency(),
            'buckarooWebsiteKey' => $this->getWebsiteKey(),
        ];
    }

    /**
     * Get Buckaroo website key
     *
     * @throws NoSuchEntityException
     *
     * @return string
     */
    protected function getWebsiteKey()
    {
        return $this->encryptor->decrypt(
            $this->configProviderAccount->getMerchantKey(
                $this->_storeManager->getStore()
            )
        );
    }

    /**
     * Get shop currency
     *
     * @return string
     */
    protected function getCurrency()
    {
        return $this->_storeManager
            ->getStore()
            ->getCurrentCurrency()
            ->getCode();
    }

    /**
     * Check if iDIN verification is required for this product
     *
     * @return bool
     */
    public function isIdinVerificationRequired()
    {
        $idinMode = (int)$this->configProviderAccount->getIdinMode();

        if (!$this->configProviderAccount->getIdin() || $idinMode === null) {
            return false;
        }

        if ($idinMode === 0) {
            return true;
        } elseif ($idinMode === 1) {
            return $this->isProductPageWithIdinRequired();
        } elseif ($idinMode === 2) {
            return $this->isInIdinRequiredCategory();
        }

        return false;
    }

    /**
     * Check if current product page requires iDIN (Per Product mode)
     *
     * @return bool
     */
    private function isProductPageWithIdinRequired()
    {
        try {
            $product = $this->getProduct();
            if (!$product) {
                return false; // Not a product page or cart page
            }
            $customAttribute = $product->getCustomAttribute('buckaroo_product_idin');
            return $customAttribute !== null && $customAttribute->getValue() == 1;
        } catch (LocalizedException $e) {
            return false; // Not a product page
        }
    }

    /**
     * Check if products are in iDIN required category (Per Category mode)
     *
     * @return bool
     */
    private function isInIdinRequiredCategory()
    {
        try {
            $product = $this->getProduct();
            if (!$product) {
                return false;
            }

            $idinCategories = explode(',', (string)$this->configProviderAccount->getIdinCategory());
            foreach ($product->getCategoryIds() as $category) {
                if (in_array($category, $idinCategories)) {
                    return true;
                }
            }
            return false;
        } catch (LocalizedException $e) {
            return false; // Not a product page
        }
    }

    /**
     * Retrieve current product model (returns null if not on product page)
     *
     * @return Product|null
     */
    private function getProduct()
    {
        if ($this->product === null && $this->registry) {
            $this->product = $this->registry->registry('product');
        }

        // Return null if no product or invalid product
        return ($this->product && $this->product->getId()) ? $this->product : null;
    }
}
