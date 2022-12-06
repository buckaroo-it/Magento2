<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class ApplepayDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        return [
            'paymentData' => base64_encode(
                (string)$this->getPayment()->getAdditionalInformation('applepayTransaction')
            ),
            'customerCardName' => $this->getCustomerCardName(),
        ];
    }

    /**
     * Get customer card name from Apple Pay transaction
     *
     * @return string|null
     */
    protected function getCustomerCardName()
    {
        $billingContact = \json_decode(
            (string)$this->getPayment()->getAdditionalInformation('billingContact')
        );
        if ($billingContact &&
            !empty($billingContact->givenName) &&
            !empty($billingContact->familyName)
        ) {
            return $billingContact->givenName . ' ' . $billingContact->familyName;
        }

        return null;
    }
}
