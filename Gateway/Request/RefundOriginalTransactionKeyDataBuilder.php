<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundOriginalTransactionKeyDataBuilder implements BuilderInterface
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $originalTransactionKey = $this->getRefundTransactionPartialSupport($payment);

        return ['originalTransactionKey' => $originalTransactionKey];
    }

    /**
     * Get Refund Transaction Partial Support KEY
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return mixed
     */
    protected function getRefundTransactionPartialSupport($payment)
    {
        $creditmemo = $payment->getCreditmemo();
        if ($payment->getMethodInstance()->canRefundPartialPerInvoice() && $creditmemo) {
            return $payment->getParentTransactionId();
        }

        return $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
    }
}
