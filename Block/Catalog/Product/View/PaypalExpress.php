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
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @param Context $context
     * @param Account $configProviderAccount
     * @param Encryptor $encryptor
     * @param Paypal $paypalConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        Encryptor $encryptor,
        Paypal $paypalConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProviderAccount = $configProviderAccount;
        $this->encryptor = $encryptor;
        $this->paypalConfig = $paypalConfig;
    }

    /**
     * Can show PayPal Express button on cart
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canShowProductButton()
    {
        return $this->paypalConfig->canShowButtonForPage(
            'Product',
            $this->_storeManager->getStore()
        );
    }

    /**
     * Can show PayPal Express button on cart
     *
     * @return bool
     * @throws NoSuchEntityException
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
     * Get shop currency
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getCurrency()
    {
        return $this->_storeManager
            ->getStore()
            ->getCurrentCurrency()
            ->getCode();
    }

    /**
     * Get buckaroo website key
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws \Exception
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
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getMerchantId()
    {
        return $this->paypalConfig->getExpressMerchantId(
            $this->_storeManager->getStore()
        );
    }

    /**
     * Get paypal express button color
     *
     * @return string|null
     * @throws NoSuchEntityException
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
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getButtonShape()
    {
        return $this->paypalConfig->getButtonShape(
            $this->_storeManager->getStore()
        );
    }

    /**
     * Get paypal express button color
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isTestMode()
    {
        return $this->paypalConfig->getActive(
            $this->_storeManager->getStore()
        ) == 1;
    }
}
