<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

/**
 * An order placed with the Voucher method is paid by the partial payments applied beforehand;
 * placing it sends nothing to the gateway. So it may only be placed once those partial payments
 * cover the total and at least one of them is a voucher. Otherwise it would wait for a payment that
 * never comes, or be recorded as a voucher order although no voucher was used.
 */
class VoucherOrderCoverageGuard implements ObserverInterface
{
    /**
     * Same tolerance the order command uses to skip a fully paid giftcard order
     */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(PaymentGroupTransaction $groupTransaction)
    {
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Observes sales_model_service_quote_submit_before
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order || $order->getPayment() === null) {
            return;
        }

        if ($order->getPayment()->getMethod() !== Voucher::CODE) {
            return;
        }

        // The module itself submits an uncovered quote to record (and cancel) the order once the
        // payment outcome is known, e.g. after a failed or cancelled partial payment. Only orders
        // the shopper places are checked here.
        $quote = $observer->getEvent()->getQuote();
        if ($quote instanceof Quote && $quote->getData(VerificationGuard::FLAG_ALREADY_CHECKED)) {
            return;
        }

        $incrementId = $order->getIncrementId();
        $alreadyPaid = (float)$this->groupTransaction->getAlreadyPaid($incrementId);
        if ($alreadyPaid >= (float)$order->getGrandTotal() - self::AMOUNT_TOLERANCE
            && $this->hasVoucherPayment($incrementId)
        ) {
            return;
        }

        throw new LocalizedException(
            __('Your vouchers do not cover the order total yet. Apply a voucher for the remaining amount or choose another payment method.')
        );
    }

    /**
     * Whether a voucher is among the successful partial payments of the order
     *
     * @param string|null $incrementId
     *
     * @return bool
     */
    private function hasVoucherPayment(?string $incrementId): bool
    {
        foreach ($this->groupTransaction->getGroupTransactionItems($incrementId) as $item) {
            if ($item['servicecode'] === Voucher::SERVICE_CODE) {
                return true;
            }
        }

        return false;
    }
}
