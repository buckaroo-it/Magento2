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
namespace TIG\Buckaroo\Service\CreditManagement\ServiceParameters;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class CreateCreditNote
{
    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    public function get($payment)
    {
        $savedfInvoiceKey = $payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (strlen($savedfInvoiceKey) <= 0) {
            return [];
        }

        /** @var Order $order */
        $order = $payment->getOrder();

        $services = [
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

        return $services;
    }
}
