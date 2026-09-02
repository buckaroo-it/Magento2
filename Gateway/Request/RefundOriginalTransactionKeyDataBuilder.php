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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class RefundOriginalTransactionKeyDataBuilder implements BuilderInterface
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $originalTransactionKey = $this->getRefundTransactionPartialSupport($payment);

        return ['originalTransactionKey' => $originalTransactionKey];
    }

    /**
     * Get Refund Transaction Partial Support KEY
     *
     * @param InfoInterface $payment
     *
     * @throws LocalizedException
     *
     * @return mixed
     */
    protected function getRefundTransactionPartialSupport(InfoInterface $payment)
    {
        $creditmemo = $payment->getCreditmemo();

        $actualTransactionKey = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_TRANSACTION_KEY);
        if (!empty($actualTransactionKey)) {
            return $actualTransactionKey;
        }

        $klarnaKpCaptureKey = $this->getKlarnaKpCaptureKey($payment);
        if ($klarnaKpCaptureKey !== null) {
            return $klarnaKpCaptureKey;
        }

        $methodInstance = $payment->getMethodInstance();
        if ($methodInstance && $methodInstance->canRefundPartialPerInvoice() && $creditmemo) {
            return $payment->getParentTransactionId();
        }

        if ($payment->getMethod() === 'buckaroo_magento2_giftcards' && $creditmemo) {
            $parentTransactionId = $payment->getParentTransactionId();
            if (!empty($parentTransactionId)) {
                return $parentTransactionId;
            }
        }

        return $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
    }

    /**
     * Capture transaction key for a captured Klarna KP order, or null when not applicable.
     *
     * Klarna KP is a two-step auth→capture method; refunds must target the capture transaction,
     * not the authorization stored in buckaroo_original_transaction_key.
     *
     * @param InfoInterface $payment
     * @return string|null
     */
    private function getKlarnaKpCaptureKey(InfoInterface $payment): ?string
    {
        $method = $payment->getMethod();
        if ($method !== 'buckaroo_magento2_klarnakp' && $method !== 'buckaroo_magento2_klarna') {
            return null;
        }

        $captureTransactionKey = $payment->getAdditionalInformation('buckaroo_capture_transaction_key');
        if (!empty($captureTransactionKey) && $payment->getAdditionalInformation('buckaroo_already_captured')) {
            return $captureTransactionKey;
        }

        return null;
    }
}
