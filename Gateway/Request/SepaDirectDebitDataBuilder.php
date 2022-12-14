<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class SepaDirectDebitDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $customerBic = $payment->getAdditionalInformation('customer_bic');
        $customerIban = $payment->getAdditionalInformation('customer_iban');
        $customerAccountName = $payment->getAdditionalInformation('customer_account_name');

        $data = [
            'iban' => $customerIban,
            'customer' => [
                'name' => $customerAccountName
            ]];

        if (!empty($customerBic)) {
            $data = ['bic' => $customerBic];
        }

        return $data;
    }
}
