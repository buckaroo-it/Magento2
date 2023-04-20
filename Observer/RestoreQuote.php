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

use Buckaroo\Magento2\Helper\Data;
use Magento\Checkout\Model\Session;
use Buckaroo\Magento2\Model\Service\Order;
use Buckaroo\Magento2\Model\Method\Payconiq;
use Buckaroo\Magento2\Model\Method\Giftcards;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemove;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $accountConfig;

    /**
     * @var \Buckaroo\Magento2\Helper\Data
     */
    private \Buckaroo\Magento2\Helper\Data $helper;

 

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Buckaroo\Magento2\Model\Service\Order
     */
    protected $orderService;

    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Remove
     */
    protected $giftcardRemoveService;

     /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;


    /**
     * @param Session $checkoutSession
     * @param Account $accountConfig
     * @param Data $helper
     * @param QuoteRecreate $quoteRecreate
     * @param CartRepositoryInterface $quoteRepository
     * @param Order $orderService
     */
    public function __construct(
        \Magento\Checkout\Model\Session                 $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Helper\Data                  $helper,
        \Magento\Quote\Api\CartRepositoryInterface      $quoteRepository,
        \Buckaroo\Magento2\Model\Service\Order          $orderService,
        GiftcardRemove $giftcardRemoveService,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $accountConfig;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->orderService = $orderService;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Restore Quote and Cancel LastRealOrder
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->helper->addDebug(__METHOD__ . '|1|');

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        $previousOrderId = $lastRealOrder->getId();

        if ($payment = $lastRealOrder->getPayment()) {
            if ($this->shouldSkipFurtherEventHandling()
                || strpos($payment->getMethod(), 'buckaroo_magento2') === false
                || in_array($payment->getMethod(), [Giftcards::PAYMENT_METHOD_CODE, Payconiq::PAYMENT_METHOD_CODE])) {
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
                    $shippingAddress = $quote->getShippingAddress();
                    if (!$shippingAddress->getShippingMethod()) {
                        $this->helper->addDebug(__METHOD__ . '|35|');
                        $shippingAddress->load($shippingAddress->getAddressId());
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
                    $this->rollbackPartialPayment($lastRealOrder->getIncrementId());
                    $this->setOrderToCancel($previousOrderId);
                }
            }

            $this->helper->addDebug(__METHOD__ . '|50|');
            $this->helper->setRestoreQuoteLastOrder(false);
            $this->checkoutSession->unsBuckarooFailedMaxAttempts();
        }

        $this->helper->addDebug(__METHOD__ . '|55|');
    }

    public function canRestoreFailedFromSpam()
    {
        return $this->helper->getRestoreQuoteLastOrder() &&
            $this->checkoutSession->getBuckarooFailedMaxAttempts() === true;
    }

    public function shouldSkipFurtherEventHandling()
    {
        return false;
    }

    /**
     * Set previous order id on the payment object for the next payment
     *
     * @param int $previousOrderId
     *
     * @return void
     */
    private function setOrderToCancel($previousOrderId)
    {
        $this->checkoutSession->getQuote()
        ->getPayment()
        ->setAdditionalInformation('buckaroo_cancel_order_id', $previousOrderId);
        $this->quoteRepository->save($this->checkoutSession->getQuote());
    }

    public function rollbackPartialPayment($incrementId)
    {
        try {
            $transactions = $this->groupTransaction->getGroupTransactionItems($incrementId);
            foreach ($transactions as $transaction) {
                $this->giftcardRemoveService->remove($transaction->getTransactionId(), $incrementId);
            }
        } catch (\Throwable $th) {
            $this->helper->addDebug(__METHOD__ . (string)$th);
        }
       
    }
}
