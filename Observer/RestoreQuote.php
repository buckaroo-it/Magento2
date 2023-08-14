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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Payconiq;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemove;
use Buckaroo\Magento2\Model\Service\Order;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;

class RestoreQuote implements ObserverInterface
{
    /**
     * @var Account
     */
    protected $accountConfig;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Order
     */
    protected $orderService;

    /**
     * @var GiftcardRemove
     */
    protected $giftcardRemoveService;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @param Session $checkoutSession
     * @param Account $accountConfig
     * @param Data $helper
     * @param CartRepositoryInterface $quoteRepository
     * @param Order $orderService
     * @param GiftcardRemove $giftcardRemoveService
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(
        Session $checkoutSession,
        Account $accountConfig,
        Data $helper,
        CartRepositoryInterface $quoteRepository,
        Order $orderService,
        GiftcardRemove $giftcardRemoveService,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->orderService = $orderService;
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $accountConfig;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Restore Quote and Cancel LastRealOrder
     *
     * @param Observer $observer
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $this->helper->addDebug(__METHOD__ . '|1|');

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        $previousOrderId = $lastRealOrder->getId();

        if ($payment = $lastRealOrder->getPayment()) {
            if ($this->shouldSkipFurtherEventHandling()
                || strpos($payment->getMethod(), 'buckaroo_magento2') === false
                || in_array($payment->getMethod(), [Payconiq::PAYMENT_METHOD_CODE])) {
                $this->helper->addDebug(__METHOD__ . '|10|');
                return;
            }

            if ($this->accountConfig->getCartKeepAlive($lastRealOrder->getStore())) {
                $this->helper->addDebug(__METHOD__ . '|20|');

                if ($this->checkoutSession->getQuote()
                    && $this->checkoutSession->getQuote()->getId()
                    && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
                ) {
                    $this->helper->addDebug(__METHOD__ . '|25|');
                    if ($shippingAddress = $quote->getShippingAddress()) {
                        if (!$shippingAddress->getShippingMethod()) {
                            $this->helper->addDebug(__METHOD__ . '|35|');
                            $shippingAddress->load($shippingAddress->getAddressId());
                        }
                    }
                }

                if (
                    (
                        $this->helper->getRestoreQuoteLastOrder() &&
                        ($lastRealOrder->getData('state') === 'new') &&
                        ($lastRealOrder->getData('status') === 'pending') &&
                        $payment->getMethodInstance()->usesRedirect
                    ) || $this->canRestoreFailedFromSpam()
                ) {
                    $this->helper->addDebug(__METHOD__ . '|40|');
                    $this->checkoutSession->restoreQuote();
                    $this->rollbackPartialPayment($lastRealOrder->getIncrementId(), $payment);
                    $this->setOrderToCancel($previousOrderId);
                }
            }

            $this->helper->addDebug(__METHOD__ . '|50|');
            $this->helper->setRestoreQuoteLastOrder(false);
            $this->checkoutSession->unsBuckarooFailedMaxAttempts();
        }

        $this->helper->addDebug(__METHOD__ . '|55|');
    }

    /**
     * Skip restore quote
     *
     * @return false
     */
    public function shouldSkipFurtherEventHandling(): bool
    {
        return false;
    }

    /**
     * Check if order has failed from max spam payment attempts
     *
     * @return boolean
     */
    public function canRestoreFailedFromSpam()
    {
        return $this->helper->getRestoreQuoteLastOrder() &&
            $this->checkoutSession->getBuckarooFailedMaxAttempts() === true;
    }

    /**
     * Rollback Partial Payment
     *
     * @param string $incrementId
     * @return void
     */
    public function rollbackPartialPayment(string $incrementId, $payment): void
    {
        try {
            $transactions = $this->groupTransaction->getGroupTransactionItems($incrementId);
            foreach ($transactions as $transaction) {
                $this->giftcardRemoveService->remove($transaction->getTransactionId(), $incrementId, $payment);
            }
        } catch (\Throwable $th) {
            $this->helper->addDebug(__METHOD__ . $th);
        }

    }

    /**
     * Set previous order id on the payment object for the next payment
     *
     * @param int $previousOrderId
     * @return void
     * @throws LocalizedException
     */
    private function setOrderToCancel(int $previousOrderId)
    {
        $this->checkoutSession->getQuote()
            ->getPayment()
            ->setAdditionalInformation('buckaroo_cancel_order_id', $previousOrderId);
        $this->quoteRepository->save($this->checkoutSession->getQuote());
    }
}
