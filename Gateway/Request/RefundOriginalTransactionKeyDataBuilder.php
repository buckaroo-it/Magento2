<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundOriginalTransactionKeyDataBuilder extends AbstractDataBuilder
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        $originalTransactionKey = $this->getRefundTransactionPartialSupport($this->getPayment());

        return ['originalTransactionKey' => $originalTransactionKey];
    }

    protected function getRefundTransactionPartialSupport($payment)
    {
        $creditmemo = $payment->getCreditmemo();
        if ($this->getPayment()->getMethodInstance()->canRefundPartialPerInvoice() && $creditmemo) {
            return $payment->getParentTransactionId();
        }

        return $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
    }
}
