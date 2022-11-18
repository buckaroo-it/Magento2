<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class SepaDirectDebitDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $paymentInfo = $this->getPayment();

        $customerBic = $paymentInfo->getAdditionalInformation('customer_bic');
        $customerIban = $paymentInfo->getAdditionalInformation('customer_iban');
        $customerAccountName = $paymentInfo->getAdditionalInformation('customer_account_name');

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
