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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

/**
 * Invoice number for a refund.
 *
 * Refund invoice numbers have to be unique, so a second refund on the same order cannot reuse
 * the first one: Riverty rejects it with "invoice number already exists (creditNoteNumber)".
 * Only the SECOND refund onwards is numbered, so single-refund orders keep the plain order
 * number they have always sent. Reachable once an order can be captured per shipment.
 */
class RefundInvoiceNumberDataBuilder implements BuilderInterface
{
    /**
     * Suffix marking the number as belonging to a credit note rather than a capture.
     */
    private const REFUND_SUFFIX = '-R';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $order = SubjectReader::readPayment($buildSubject)->getOrder()->getOrder();

        return [
            'invoice' => $this->getRefundInvoiceNumber($order),
            'order' => $order->getIncrementId(),
        ];
    }

    /**
     * The plain order number for the first refund, a numbered one for every refund after it.
     *
     * The counter is the number of credit memos already stored on the order, so the refund in
     * flight - which Magento saves only after the gateway has answered - gets the next number.
     *
     * @param Order $order
     *
     * @return string
     */
    private function getRefundInvoiceNumber(Order $order): string
    {
        $refundNumber = $this->countStoredCreditmemos($order) + 1;

        if ($refundNumber === 1) {
            return (string)$order->getIncrementId();
        }

        return $order->getIncrementId() . self::REFUND_SUFFIX . $refundNumber;
    }

    /**
     * Number of credit memos already persisted against the order.
     *
     * @param Order $order
     *
     * @return int
     */
    private function countStoredCreditmemos(Order $order): int
    {
        $stored = 0;

        foreach ($order->getCreditmemosCollection() as $creditmemo) {
            if ($creditmemo->getId()) {
                $stored++;
            }
        }

        return $stored;
    }
}
