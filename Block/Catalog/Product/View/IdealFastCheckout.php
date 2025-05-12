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
use Magento\Framework\Encryption\Encryptor;
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
    protected Repository $assetRepo;


    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Ideal $idealConfig,
        Repository $assetRepo,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->idealConfig = $idealConfig;
        $this->assetRepo = $assetRepo;

    }

    /**
     * Determine if the product button can be shown
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
     * @return mixed
     */
    public function getLogo() {

        $logoColor = $this->idealConfig->getLogoColor($this->_storeManager->getStore());

        if ($logoColor == "Light"){
            $name = "ideal/ideal-fast-checkout-rgb-light.png";
        }
        else{
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
}
