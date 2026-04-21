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

namespace Buckaroo\Magento2\Block\Catalog\Product\View;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class PaypalExpress extends Template
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
     * @var Paypal
     */
    protected $paypalConfig;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var CheckoutSession|null
     */
    private $checkoutSession;

    /**
     * @param Context              $context
     * @param Account              $configProviderAccount
     * @param Encryptor            $encryptor
     * @param Paypal               $paypalConfig
     * @param Registry|null        $registry
     * @param CheckoutSession|null $checkoutSession
     * @param array                $data
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Paypal $paypalConfig,
        ?Registry $registry = null,
        ?CheckoutSession $checkoutSession = null,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->paypalConfig = $paypalConfig;
        $this->registry = $registry;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Can show PayPal Express button on cart
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function canShowProductButton()
    {
        return $this->paypalConfig->canShowButtonForPage(
            'Product',
            $this->_storeManager->getStore()
        );
    }

    /**
     * Can show the PayPal Express button on the cart
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function canShowCartButton()
    {
        return $this->paypalConfig->canShowButtonForPage(
            'Cart',
            $this->_storeManager->getStore()
        );
    }

    /**
     * Get all data required
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'currency' => $this->getCurrency(),
            'buckarooWebsiteKey' => $this->getWebsiteKey(),
            'paypalMerchantId' => $this->getMerchantId(),
            'style' => [
                "color" => $this->getButtonColor(),
                "shape" => $this->getButtonShape(),
            ],
            'isTestMode' => $this->isTestMode()
        ];
    }

    /**
     * Config for the cart-page PayPal Express button.
     *
     * Extends {@see getConfig()} with the current quote grand total and currency so the
     * JS never renders the PayPal button with a null `initialAmount`. Mirrors the Mollie
     * Magento2 plugin's server-side seeding pattern (Block/Applepay/Shortcut/Button.php)
     * and avoids the known race where {@see \Magento\Checkout\Model\Session} quote totals
     * load after the button has already mounted client-side.
     *
     * @return array
     */
    public function getCartConfig(): array
    {
        $config = $this->getConfig();
        $grandTotal = $this->getCartGrandTotal();

        if ($grandTotal !== null) {
            $config['amount'] = number_format($grandTotal, 2, '.', '');
        }

        $quoteCurrency = $this->getCartCurrency();
        if ($quoteCurrency !== null) {
            $config['currency'] = $quoteCurrency;
        }

        return $config;
    }

    /**
     * Current quote grand total, or null when no quote is available.
     *
     * @return float|null
     */
    private function getCartGrandTotal(): ?float
    {
        $quote = $this->getActiveQuote();
        if ($quote === null || !$quote->getId()) {
            return null;
        }

        $grandTotal = (float)$quote->getData('grand_total');
        return $grandTotal > 0 ? $grandTotal : null;
    }

    /**
     * Quote currency code when set, otherwise null (callers fall back to the store currency).
     *
     * @return string|null
     */
    private function getCartCurrency(): ?string
    {
        $quote = $this->getActiveQuote();
        if ($quote === null) {
            return null;
        }

        $currency = $quote->getData('quote_currency_code');
        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    /**
     * Return the active checkout quote or null if it cannot be loaded.
     *
     * @return \Magento\Quote\Model\Quote|null
     */
    private function getActiveQuote()
    {
        if ($this->checkoutSession === null) {
            return null;
        }

        try {
            return $this->checkoutSession->getQuote();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get shop currency
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
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
     * Get buckaroo store key
     *
     * @throws NoSuchEntityException
     * @throws \Exception
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
     * Get merchant id
     *
     * @throws NoSuchEntityException
     *
     * @return string|null
     */
    protected function getMerchantId()
    {
        return $this->paypalConfig->getExpressMerchantId(
            $this->_storeManager->getStore()
        );
    }

    /**
     * Get PayPal express button color
     *
     * @throws NoSuchEntityException
     *
     * @return string|null
     */
    protected function getButtonColor()
    {
        return $this->paypalConfig->getButtonColor(
            $this->_storeManager->getStore()
        );
    }

    /**
     * Get paypal express button color
     *
     * @throws NoSuchEntityException
     *
     * @return string|null
     */
    protected function getButtonShape()
    {
        return $this->paypalConfig->getButtonShape(
            $this->_storeManager->getStore()
        );
    }

    /**
     * Check if PayPal is in test mode
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    protected function isTestMode()
    {
        return $this->paypalConfig->getActive(
            $this->_storeManager->getStore()
        ) == 1;
    }

    /**
     * Check if iDIN verification is required for this context (product page or cart)
     *
     * @return bool
     */
    public function isIdinVerificationRequired()
    {
        $idinMode = (int)$this->configProviderAccount->getIdinMode();

        // Check if iDIN is enabled
        if (!$this->configProviderAccount->getIdin() || $idinMode === null) {
            return false;
        }

        if ($idinMode === 0) {
            // Global mode - required for all products/contexts
            return true;
        } elseif ($idinMode === 1) {
            // Per Product mode - check if we're on product page and if product requires iDIN
            return $this->isProductPageWithIdinRequired();
        } elseif ($idinMode === 2) {
            // Per Category mode - check if current product(s) are in selected categories
            return $this->isInIdinRequiredCategory();
        }

        return false;
    }

    /**
     * Check if the current product page requires iDIN (Per Product mode)
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
     * Retrieve the current product model (returns null if not on the product page)
     *
     * @return Product|null
     */
    private function getProduct()
    {
        if ($this->product === null && $this->registry) {
            $this->product = $this->registry->registry('product');
        }

        // Return null if no product or invalid product (e.g., on cart page)
        return ($this->product && $this->product->getId()) ? $this->product : null;
    }
}
