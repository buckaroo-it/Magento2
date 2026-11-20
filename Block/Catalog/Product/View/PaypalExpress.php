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

use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\View\Element\Template;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\View\Element\Template\Context;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;

class PaypalExpress extends Template
{
    /**
     * Buckaroo client SDK served by the test checkout
     */
    public const TEST_SDK_URL = '//testcheckout.buckaroo.nl/api/buckaroosdk/script';

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $configProviderAccount;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal
     */
    protected $paypalConfig;

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
    public function canShowButton($page)
    {
        return $this->isModuleActive() &&
            $this->paypalConfig->canShowButtonForPage($page, $this->_storeManager->getStore()) &&
            $this->paypalConfig->isPayPalEnabled($this->_storeManager->getStore());
    }

    /**
     * Check if PayPal is in test mode
     *
     * @return bool
     */
    public function isTestMode()
    {
        return $this->paypalConfig->isPayPalEnabled($this->_storeManager->getStore()) == Data::MODE_TEST;
    }

    /**
     * Get the RequireJS configuration that loads the Buckaroo SDK from the test checkout
     *
     * @return string
     */
    public function getTestSdkRequireConfig()
    {
        return 'require.config({map:{"*":{"BuckarooSDK":' . json_encode(self::TEST_SDK_URL) . '}}});';
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
     * Get all data required
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'currency' => $this->getCurrency(),
            'buckarooWebsiteKey' => $this->getWebsiteKey(),
            'paypalMerchantId' => $this->getMerchantId(),
        ];
    }
    /**
     * Get buckaroo website key
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
        if ($this->isTestMode()) {
            $sandboxMerchantId = $this->paypalConfig->getExpressSandboxMerchantId(
                $this->_storeManager->getStore()
            );

            if (!empty($sandboxMerchantId)) {
                return $sandboxMerchantId;
            }
        }

        return $this->paypalConfig->getExpressMerchantId(
            $this->_storeManager->getStore()
        );
    }
}
