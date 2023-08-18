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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Gateway\Http\Client\Json;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class OrderCancelAfter implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Json
     */
    protected $client;

    /**
     * @var Encryptor
     */
    private $encryptor;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var PayPerEmail
     */
    protected $configProviderPPE;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $client
     * @param Encryptor $encryptor
     * @param Account $configProviderAccount
     * @param PayPerEmail $configProviderPPE
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $client,
        Encryptor $encryptor,
        Account $configProviderAccount,
        PayPerEmail $configProviderPPE,
        BuckarooLoggerInterface $logger
    ) {
        $this->scopeConfig           = $scopeConfig;
        $this->client                = $client;
        $this->encryptor             = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderPPE     = $configProviderPPE;
        $this->logger                = $logger;
    }

    /**
     * Do cancel request to payment engine for Pay Per Email payment method after order cancel
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();

        $originalKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        $cancelPPE = $this->configProviderPPE->getCancelPpe();

        if ($cancelPPE && in_array($payment->getMethodInstance()->getCode(), ['buckaroo_magento2_payperemail'])) {
            try {
                $this->logger->addDebug(__METHOD__ . '|sendCancelResponse|');
                $this->sendCancelResponse($originalKey);
                //phpcs:ignore: Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Exception $e) {
                // empty block
            }
        }
    }

    private function sendCancelResponse($key)
    {
        $active = $this->configProviderPPE->getActive();
        $mode = ($active == Data::MODE_LIVE) ? Data::MODE_LIVE : Data::MODE_TEST;

        $this->client->setSecretKey($this->encryptor->decrypt($this->configProviderAccount->getSecretKey()));
        $this->client->setWebsiteKey($this->encryptor->decrypt($this->configProviderAccount->getMerchantKey()));

        return $this->client->doCancelRequest($key, $mode);
    }
}
