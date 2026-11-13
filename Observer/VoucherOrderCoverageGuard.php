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
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * An order placed with the Voucher method is paid entirely by vouchers applied beforehand; placing
 * it sends nothing to the gateway. So it may only be placed once those vouchers cover the total,
 * otherwise it would wait for a payment that never comes.
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

        $alreadyPaid = (float)$this->groupTransaction->getAlreadyPaid($order->getIncrementId());
        if ($alreadyPaid >= (float)$order->getGrandTotal() - self::AMOUNT_TOLERANCE) {
            return;
        }

        throw new LocalizedException(
            __('Your vouchers do not cover the order total yet. Apply a voucher for the remaining amount or choose another payment method.')
        );
    }
}
