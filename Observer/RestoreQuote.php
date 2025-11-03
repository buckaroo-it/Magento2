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

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Payconiq;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemove;
use Buckaroo\Magento2\Model\Service\Order;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;

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
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param Session                 $checkoutSession
     * @param Account                 $accountConfig
     * @param BuckarooLoggerInterface $logger
     * @param CartRepositoryInterface $quoteRepository
     * @param Order                   $orderService
     * @param GiftcardRemove          $giftcardRemoveService
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(
        Session $checkoutSession,
        Account $accountConfig,
        BuckarooLoggerInterface $logger,
        CartRepositoryInterface $quoteRepository,
        Order $orderService,
        GiftcardRemove $giftcardRemoveService,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->orderService = $orderService;
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $accountConfig;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Restore Quote and Cancel LastRealOrder
     *
     * @param Observer $observer
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        $previousOrderId = $lastRealOrder->getId();

        if ($payment = $lastRealOrder->getPayment()) {
            if ($this->isValidPayment($payment)) {
                return;
            }

            if ($this->isCartKeepAlive($lastRealOrder)) {
                $this->prepareQuoteShippingAddress();

                if ($this->isNewPendingLastOrder($lastRealOrder, $payment)
                    || $this->canRestoreFailedFromSpam()
                    || $this->isCanceledLastOrderWithRedirect($lastRealOrder, $payment)
                ) {
                    $this->logger->addDebug(sprintf(
                        '[RESTORE_QUOTE] | [Observer] | [%s:%s] - Restore Quote | ' .
                        'lastRealOrder: %s - %s| previousOrderId: %s',
                        __METHOD__,
                        __LINE__,
                        $lastRealOrder->getIncrementId(),
                        $lastRealOrder->getEntityId(),
                        $previousOrderId
                    ));

                    $this->checkoutSession->restoreQuote();
                    $this->checkoutSession->getQuote()->setOrigOrderId(null);
                    $this->rollbackPartialPayment($lastRealOrder->getIncrementId(), $payment);
                    $this->setOrderToCancel($previousOrderId);
                }
            }

            $this->logger->addDebug(sprintf(
                '[RESTORE_QUOTE] | [Observer] | [%s:%s] - Restore Skipped: '
                . 'Quote restoration was not carried out. | lastRealOrder: %s',
                __METHOD__,
                __LINE__,
                $lastRealOrder->getIncrementId(),
            ));

            $this->checkoutSession->setRestoreQuoteLastOrder(false);
            $this->checkoutSession->unsBuckarooFailedMaxAttempts();
        }
    }

    /**
     * Validate payment method for restore quote logic
     *
     * @param $payment
     *
     * @return bool
     */
    private function isValidPayment($payment): bool
    {
        return $this->shouldSkipFurtherEventHandling()
            || strpos($payment->getMethod(), 'buckaroo_magento2') === false
            || in_array($payment->getMethod(), [Payconiq::CODE]);
    }

    /**
     * Check if cart keep alive is enabled for the order's store
     *
     * @param $lastRealOrder
     *
     * @return bool
     */
    private function isCartKeepAlive($lastRealOrder): bool
    {
        return $this->accountConfig->getCartKeepAlive($lastRealOrder->getStore());
    }

    /**
     * Prepare quote and shipping address if needed
     */
    private function prepareQuoteShippingAddress(): void
    {
        if ($this->checkoutSession->getQuote()
            && $this->checkoutSession->getQuote()->getId()
            && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
        ) {
            if ($shippingAddress = $quote->getShippingAddress()) {
                if (!$shippingAddress->getShippingMethod()) {
                    $shippingAddress->load($shippingAddress->getAddressId());
                }
            }
        }
    }

    /**
     * Skip restore quote
     *
     * @return false
     */
    public function shouldSkipFurtherEventHandling()
    {
        return false;
    }

    /**
     * Check if order has failed from max spam payment attempts
     *
     * @return bool
     */
    public function canRestoreFailedFromSpam()
    {
        return $this->checkoutSession->getRestoreQuoteLastOrder() &&
            $this->checkoutSession->getBuckarooFailedMaxAttempts() === true;
    }

    /**
     * Rollback Partial Payment
     *
     * @param string $incrementId
     * @param        $payment
     */
    public function rollbackPartialPayment(string $incrementId, $payment): void
    {
        try {
            $transactions = $this->groupTransaction->getGroupTransactionItems($incrementId);
            foreach ($transactions as $transaction) {
                $this->giftcardRemoveService->remove($transaction->getTransactionId(), $incrementId, $payment);
            }
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[RESTORE_QUOTE] | [Observer] | [%s:%s] - Rollback Partial Payment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }
    }

    /**
     * Set previous order id on the payment object for the next payment
     *
     * @param int $previousOrderId
     *
     * @throws LocalizedException
     */
    private function setOrderToCancel(int $previousOrderId)
    {
        $this->checkoutSession->getQuote()
            ->getPayment()
            ->setAdditionalInformation('buckaroo_cancel_order_id', $previousOrderId);
        $this->quoteRepository->save($this->checkoutSession->getQuote());
    }

    /**
     * Check if the last real order is new, pending, and uses redirect
     *
     * @param $lastRealOrder
     * @param $payment
     *
     * @return bool
     */
    private function isNewPendingLastOrder($lastRealOrder, $payment): bool
    {
        return $this->checkoutSession->getRestoreQuoteLastOrder()
            && $lastRealOrder->getData('state') === 'new'
            && $lastRealOrder->getData('status') === 'pending'
            && $payment->getMethodInstance()->usesRedirect;
    }

    /**
     * Check if the last real order is canceled and uses redirect
     *
     * @param $lastRealOrder
     * @param $payment
     *
     * @return bool
     */
    private function isCanceledLastOrderWithRedirect($lastRealOrder, $payment): bool
    {
        return $this->checkoutSession->getRestoreQuoteLastOrder()
            && $lastRealOrder->getData('state') === 'canceled'
            && $payment->getMethodInstance()->usesRedirect;
    }
}
