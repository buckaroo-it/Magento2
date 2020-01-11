<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
