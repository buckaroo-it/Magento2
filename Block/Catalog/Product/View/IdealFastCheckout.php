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
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\View\Element\Template\Context;

class IdealFastCheckout extends Template implements ShortcutInterface
{
    /**
     * Alias used when this block is added as a shortcut button.
     */
    private const SHORTCUT_ALIAS = 'buckaroo_magento2.checkout.cart.ideal.fast.checkout';

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

    /**
     * @var Ideal
     */
    protected $ideal;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var IdinConfigProvider|null
     */
    private $idinConfigProvider;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Product
     */
    private $product;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Account $configProviderAccount
     * @param Encryptor $encryptor
     * @param Ideal $idealConfig
     * @param Repository $assetRepo
     * @param Registry|null $registry
     * @param array $data
     * @param IdinConfigProvider|null $idinConfigProvider
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Ideal $idealConfig,
        Repository $assetRepo,
        ?Registry $registry = null,
        array $data = [],
        ?IdinConfigProvider $idinConfigProvider = null
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->idealConfig = $idealConfig;
        $this->assetRepo = $assetRepo;
        $this->registry = $registry;
        $this->idinConfigProvider = $idinConfigProvider;
    }

    /**
     * Get the shortcut alias used by the shortcut buttons container.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return self::SHORTCUT_ALIAS;
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
     * Return the button color variant name used by the KO template and CSS.
     *
     * @throws NoSuchEntityException
     *
     * @return string  'Magenta' or 'White'
     */
    public function getButtonVariant(): string
    {
        $stored = $this->idealConfig->getLogoColor($this->_storeManager->getStore());

        if (in_array($stored, ['White', 'Dark'], true)) {
            return 'White';
        }

        return 'Magenta';
    }

    /**
     * Get all required data
     *
     * @return array
     * @throws NoSuchEntityException
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
     * Check if iDIN verification is still required for this context (product page or cart)
     *
     * @return bool
     */
    public function isIdinVerificationRequired()
    {
        if ($this->idinConfigProvider === null) {
            $this->idinConfigProvider = ObjectManager::getInstance()->get(IdinConfigProvider::class);
        }

        return $this->idinConfigProvider->isVerificationRequiredForExpress($this->getProduct());
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
