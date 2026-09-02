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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Records the capture (Pay) transaction key on the payment.
 */
class CaptureTransactionKeyHandler implements HandlerInterface
{
    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response): void
    {
        // A completed group transaction refund carries no capture of its own.
        if (isset($response['group_transaction_refund_complete'])
            && $response['group_transaction_refund_complete'] === true
        ) {
            return;
        }

        $transactionKey = SubjectReader::readTransactionResponse($response)->getTransactionKey();

        if (empty($transactionKey)) {
            return;
        }

        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();
        $payment->setAdditionalInformation(BuckarooAdapter::BUCKAROO_CAPTURE_TRANSACTION_KEY, $transactionKey);
        $payment->setAdditionalInformation(BuckarooAdapter::BUCKAROO_ALREADY_CAPTURED, true);
    }
}
