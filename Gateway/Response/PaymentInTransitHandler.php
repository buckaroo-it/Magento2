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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentInTransitHandler implements HandlerInterface
{
    public const BUCKAROO_PAYMENT_IN_TRANSIT = 'buckaroo_payment_in_transit';

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        // Skip if refund was already completed via group transactions
        if (isset($response['group_transaction_refund_complete']) 
            && $response['group_transaction_refund_complete'] === true
        ) {
            return;
        }

        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var TransactionResponse $transaction */
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        $this->setPaymentInTransit($payment);

        if (!$transactionResponse->hasRedirect()) {
            $this->setPaymentInTransit($payment, false);
        }
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     * @param bool                  $inTransit
     */
    public function setPaymentInTransit(OrderPaymentInterface $payment, bool $inTransit = true): void
    {
        $payment->setAdditionalInformation(self::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
    }
}
