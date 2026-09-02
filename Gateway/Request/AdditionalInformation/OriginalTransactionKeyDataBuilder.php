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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class OriginalTransactionKeyDataBuilder implements BuilderInterface
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDO->getPayment();

        $actualTransactionKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_TRANSACTION_KEY);
        if (!empty($actualTransactionKey)) {
            return ['originalTransactionKey' => $actualTransactionKey];
        }

        $klarnaPaymentMethods = ['buckaroo_magento2_klarnakp', 'buckaroo_magento2_klarna'];
        if (in_array($payment->getMethod(), $klarnaPaymentMethods)) {
            $dataRequestKey = $this->getDataRequestKey($paymentDO, $payment);

            $invoiceTransactionId = $this->getRefundedInvoiceTransactionId($payment);
            if (!empty($invoiceTransactionId)) {
                if ($this->isDataRequestKey($invoiceTransactionId, $dataRequestKey)) {
                    $this->logCorruptKey('invoice transaction id', $invoiceTransactionId, $paymentDO);
                } else {
                    return ['originalTransactionKey' => $invoiceTransactionId];
                }
            }

            $captureTransactionKey = $payment->getAdditionalInformation(
                BuckarooAdapter::BUCKAROO_CAPTURE_TRANSACTION_KEY
            );

            if (!empty($captureTransactionKey)
                && $this->isDataRequestKey($captureTransactionKey, $dataRequestKey)
            ) {
                $this->logCorruptKey('capture transaction key', $captureTransactionKey, $paymentDO);
            } elseif (!empty($captureTransactionKey)
                && $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ALREADY_CAPTURED)
            ) {
                return ['originalTransactionKey' => $captureTransactionKey];
            }
        }

        $originalTransactionKey = $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        return ['originalTransactionKey' => $originalTransactionKey];
    }

    /**
     * Resolve the Klarna MOR Reserve DataRequest key.
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @param InfoInterface $payment
     *
     * @return string|null
     */
    private function getDataRequestKey(PaymentDataObjectInterface $paymentDO, InfoInterface $payment): ?string
    {
        $paymentKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_DATAREQUEST_KEY);
        if (!empty($paymentKey)) {
            return (string)$paymentKey;
        }

        $orderAdapter = $paymentDO->getOrder();
        if (!$orderAdapter instanceof OrderAdapter) {
            return null;
        }

        $orderKey = $orderAdapter->getOrder()->getBuckarooDatarequestKey();

        return !empty($orderKey) ? (string)$orderKey : null;
    }

    /**
     * Whether the candidate refund target is the Reserve DataRequest key, which the gateway rejects.
     *
     * @param string $candidateKey
     * @param string|null $dataRequestKey
     *
     * @return bool
     */
    private function isDataRequestKey(string $candidateKey, ?string $dataRequestKey): bool
    {
        return $dataRequestKey !== null && strcasecmp(trim($candidateKey), trim($dataRequestKey)) === 0;
    }

    /**
     * Record that a stored key was rejected as a refund target, so corrupted orders stay visible.
     *
     * @param string $source
     * @param string $key
     * @param PaymentDataObjectInterface $paymentDO
     *
     * @return void
     */
    private function logCorruptKey(string $source, string $key, PaymentDataObjectInterface $paymentDO): void
    {
        $this->logger->addWarning(sprintf(
            '[REFUND] | [%s:%s] - Ignoring %s %s for order %s: it is the Klarna Reserve DataRequest key, '
            . 'which the gateway rejects for refunds.',
            __METHOD__,
            __LINE__,
            $source,
            $key,
            $paymentDO->getOrder()->getOrderIncrementId()
        ));
    }

    /**
     * Get the capture transaction id of the invoice the current credit memo refunds, if any.
     *
     * @param InfoInterface $payment
     *
     * @return string|null
     */
    private function getRefundedInvoiceTransactionId(InfoInterface $payment): ?string
    {
        if (!$payment instanceof \Magento\Sales\Model\Order\Payment) {
            $this->logger->addDebug(sprintf(
                '[REFUND] | [%s:%s] - Payment is a %s, cannot resolve the refunded invoice; '
                . 'falling back to the stored transaction keys.',
                __METHOD__,
                __LINE__,
                get_class($payment)
            ));

            return null;
        }

        $creditmemo = $payment->getCreditmemo();
        if ($creditmemo === null) {
            return null;
        }

        $invoice = $creditmemo->getInvoice();
        if ($invoice === null) {
            return null;
        }

        $transactionId = $invoice->getTransactionId();

        return !empty($transactionId) ? (string)$transactionId : null;
    }
}
