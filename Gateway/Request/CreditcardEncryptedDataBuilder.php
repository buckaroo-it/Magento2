<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class CreditcardEncryptedDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $additionalInformation = $this->getPayment()->getAdditionalInformation();

        if (!isset($additionalInformation['customer_encrypteddata'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the encrypted creditcard data to Buckaroo.'));
        }

        if (!isset($additionalInformation['customer_creditcardcompany'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the creditcard company data to Buckaroo.'));
        }

        return [
            'name' => $additionalInformation['customer_creditcardcompany'],
            'encryptedCardData' => $additionalInformation['customer_encrypteddata']
        ];
    }
}
