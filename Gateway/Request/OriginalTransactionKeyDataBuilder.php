<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class OriginalTransactionKeyDataBuilder implements BuilderInterface
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $payment = $paymentDO->getPayment();

        $originalTransactionKey = $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);

        return ['originalTransactionKey' => $originalTransactionKey];
    }
}
