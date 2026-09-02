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

namespace Buckaroo\Magento2\Service\CreditManagement\ServiceParameters;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class CreateCreditNote
{
    /**
     * Set services parameters for credit not credit management
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    public function get($payment): array
    {
        $savedfInvoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (strlen($savedfInvoiceKey) <= 0) {
            return [];
        }

        /** @var Order $order */
        $order = $payment->getOrder();

        return [
            'Name'             => 'CreditManagement3',
            'Action'           => 'CreateCreditNote',
            'Version'          => 1,
            'RequestParameter' => [
                [
                    '_'    => $order->getGrandTotal(),
                    'Name' => 'InvoiceAmount',
                ],
                [
                    '_'    => $order->getTaxAmount(),
                    'Name' => 'InvoiceAmountVat',
                ],
                [
                    '_'    => date('Y-m-d'),
                    'Name' => 'InvoiceDate',
                ],
                [
                    '_'    => $order->getIncrementId(),
                    'Name' => 'OriginalInvoiceNumber',
                ],
            ],
        ];
    }
}
