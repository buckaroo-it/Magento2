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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;

class OrderCancelAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $client;

    /** @var Encryptor $encryptor */
    private $encryptor;

    protected $configProviderAccount;

    protected $configProviderPPE;

    protected $logging;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Buckaroo\Magento2\Gateway\Http\Client\Json $client,
        Encryptor $encryptor,
        Account $configProviderAccount,
        PayPerEmail $configProviderPPE,
        Log $logging
    ) {
        $this->scopeConfig           = $scopeConfig;
        $this->client                = $client;
        $this->encryptor             = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderPPE     = $configProviderPPE;
        $this->logging               = $logging;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /* @var $order \Magento\Sales\Model\Order */
        $order = $observer->getEvent()->getOrder();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var $quote \Magento\Quote\Model\Quote
         */
        $quote = $observer->getEvent()->getQuote();

        $payment = $order->getPayment();

        $originalKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        $cancel_ppe = $this->configProviderPPE->getCancelPpe();

        if ($cancel_ppe && in_array($payment->getMethodInstance()->getCode(), ['buckaroo_magento2_payperemail'])
        ) {
            try {
                $this->logging->addDebug(__METHOD__ . '|sendCancelResponse|');
                $response = $this->sendCancelResponse($originalKey);
                //phpcs:ignore: Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Exception $e) {
            }
        }
    }

    private function sendCancelResponse($key)
    {
        $active = $this->configProviderPPE->getActive();
        $mode = ($active == \Buckaroo\Magento2\Helper\Data::MODE_LIVE) ?
        \Buckaroo\Magento2\Helper\Data::MODE_LIVE : \Buckaroo\Magento2\Helper\Data::MODE_TEST;

        $this->client->setSecretKey($this->encryptor->decrypt($this->configProviderAccount->getSecretKey()));
        $this->client->setWebsiteKey($this->encryptor->decrypt($this->configProviderAccount->getMerchantKey()));

        return $this->client->doCancelRequest($key, $mode);
    }
}
