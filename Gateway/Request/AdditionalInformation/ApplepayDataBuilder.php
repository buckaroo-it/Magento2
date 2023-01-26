<?php

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ApplepayDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        return [
            'paymentData' => base64_encode(
                (string)$paymentDO->getPayment()->getAdditionalInformation('applepayTransaction')
            ),
            'customerCardName' => $this->getCustomerCardName($paymentDO),
        ];
    }

    /**
     * Get customer card name from Apple Pay transaction
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @return string|null
     */
    protected function getCustomerCardName($paymentDO)
    {
        $billingContact = \json_decode(
            (string)$paymentDO->getPayment()->getAdditionalInformation('billingContact')
        );
        if (
            $billingContact &&
            !empty($billingContact->givenName) &&
            !empty($billingContact->familyName)
        ) {
            return $billingContact->givenName . ' ' . $billingContact->familyName;
        }

        return null;
    }
}
