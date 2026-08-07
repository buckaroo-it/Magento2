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
     * The key is stored on the order and, depending on which code path recorded it, also in the
     * payment's additional information. Both sources must be consulted: reading only one of them
     * silently disables the corruption guards below (BTI-1267).
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

        // getOrder() is only declared on this module's OrderAdapter, not on OrderAdapterInterface,
        // so never assume it: a fatal here would break refunds outright.
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
     * Magento stamps each invoice with the transaction id of the capture that paid it, making this
     * the per-invoice refund target. `getCreditmemo()` is only declared on the concrete
     * Order\Payment (not on InfoInterface/OrderPaymentInterface), hence the instanceof narrowing:
     * do not "simplify" it to an interface check, that would disable this precedence entirely.
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
