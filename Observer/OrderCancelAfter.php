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
use Magento\Framework\App\State as AppState;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class OrderCancelAfter implements ObserverInterface
{
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
    protected $logger;

    /**
     * @var AppState
     */
    private $appState;

    /**
     * @param Json                    $client
     * @param Encryptor               $encryptor
     * @param Account                 $configProviderAccount
     * @param PayPerEmail             $configProviderPPE
     * @param BuckarooLoggerInterface $logger
     * @param AppState                $appState
     */
    public function __construct(
        Json $client,
        Encryptor $encryptor,
        Account $configProviderAccount,
        PayPerEmail $configProviderPPE,
        BuckarooLoggerInterface $logger,
        AppState $appState
    ) {
        $this->client                = $client;
        $this->encryptor             = $encryptor;
        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderPPE     = $configProviderPPE;
        $this->logger                = $logger;
        $this->appState              = $appState;
    }

    /**
     * Do cancel the request to the payment engine for Pay Per Email payment method after order cancel.
     * Also marks merchant-initiated (admin panel) cancellations so that a subsequent successful
     * Buckaroo push does not incorrectly reactivate a deliberately canceled order.
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        $originalKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        $this->markManualCancellationIfApplicable($payment, $order);

        $cancelPPE = $this->configProviderPPE->getCancelPpe();

        if ($cancelPPE && $payment->getMethod() == PayPerEmail::CODE) {
            try {
                $this->logger->addDebug(sprintf(
                    '[CANCEL_ORDER - PayPerEmail] | [Observer] | [%s:%s] - Send Cancel Order Request for PayPerEmail' .
                    'to payment engine | originalKey: %s | order: %s',
                    __METHOD__,
                    __LINE__,
                    var_export([$originalKey], true),
                    $order->getId()
                ));
                $this->sendCancelResponse($originalKey, $order->getStoreId());
            } catch (\Exception $e) {
                $this->logger->addError(sprintf(
                    '[CANCEL_ORDER - PayPerEmail] | [Observer] | [%s:%s] - Send Cancel Request for PPE | [ERROR]: %s',
                    __METHOD__,
                    __LINE__,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * When a merchant manually cancels an order through the Magento admin panel (area = adminhtml),
     * a persistent flag is stored on the payment so that any later successful Buckaroo push cannot
     * reactivate the order. Cancellations triggered by push processors, services, or cron jobs run
     * in webapi_rest / frontend / crontab areas and are therefore excluded from this guard.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param Order $order
     * @return void
     * @throws LocalizedException
     */
    private function markManualCancellationIfApplicable($payment, Order $order): void
    {
        if (!$payment->getMethod() || strpos($payment->getMethod(), 'buckaroo_') !== 0) {
            return;
        }

        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return;
        }

        if ($areaCode !== 'adminhtml') {
            return;
        }

        $this->logger->addDebug(sprintf(
            '[CANCEL_ORDER] | [Observer] | [%s:%s] - Order %s canceled by merchant via admin panel. ' .
            'Setting buckaroo_manually_canceled to prevent push-based reactivation.',
            __METHOD__,
            __LINE__,
            $order->getIncrementId()
        ));

        $payment->setAdditionalInformation('buckaroo_manually_canceled', true);
        $payment->save();
    }

    /**
     * @param mixed      $key
     * @param null|mixed $storeId
     *
     * @throws \Exception
     */
    private function sendCancelResponse($key, $storeId = null)
    {
        $active = $this->configProviderPPE->getActive();
        $mode = ($active == Data::MODE_LIVE) ? Data::MODE_LIVE : Data::MODE_TEST;

        $secretKey = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey($storeId));
        $websiteKey = $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey($storeId));

        return $this->client->doCancelRequest($key, $mode, $secretKey, $websiteKey);
    }
}
