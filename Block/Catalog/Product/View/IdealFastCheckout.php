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

    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Ideal $idealConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->idealConfig = $idealConfig;

    }

    /**
     * Determine if the product button can be shown
     *
     * @return bool
     */
    public function canShowProductButton()
    {
        return $this->idealConfig->canShowButtonForPage(
            'Product',
            $this->_storeManager->getStore()
        );
    }

    /**
     * Determine if the cart button can be shown
     *
     * @return bool
     */
    public function canShowCartButton()
    {
        return $this->idealConfig->canShowButtonForPage(
            'Cart',
            $this->_storeManager->getStore()
        );
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
//            'idealMerchantId' => $this->getMerchantId(),
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

    /**
     * Get merchant id
     *
     * @return string|null
     */
    protected function getMerchantId()
    {
        return $this->idealConfig->getMerchantId(
            $this->_storeManager->getStore()
        );
    }

//    protected function getOrder(){
//        return $this->ideal->getOrderTransactionBuilder("Test");
//    }

    /**
     * @return false|string
     */
    public function getIdealConfig()
    {
        return json_encode($this->idealConfig->getConfig());
    }
}
