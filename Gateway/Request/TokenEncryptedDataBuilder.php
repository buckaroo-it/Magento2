<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class TokenEncryptedDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $useClientSide = $this->getConfigData('client_side');
        $additionalInformation = $this->getPayment()->getAdditionalInformation();

        if ($useClientSide && isset($additionalInformation['client_side_mode'])
            && ($additionalInformation['client_side_mode'] == 'cc')
        ) {
            if (!isset($additionalInformation['customer_encrypteddata'])) {
                throw new \Buckaroo\Magento2\Exception(
                    __('An error occured trying to send the encrypted bancontact data to Buckaroo.')
                );
            }
            return ['encryptedCardData' => $additionalInformation['customer_encrypteddata']];
        } else {
            return ['saveToken' => true];
        }
    }
}
