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
