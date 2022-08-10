<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Resources\Constants\Gender;
use Magento\Sales\Api\Data\OrderAddressInterface;

class ReserveDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $this->getOrder()->getBillingAddress();
        $country = $billingAddress->getCountryId();
        $birthDayStamp = str_replace('/', '', (string)$this->payment->getAdditionalInformation('customer_DoB'));
        $gender = Gender::FEMALE;
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            $gender = Gender::MALE;
        }
        return [
            'operatingCountry' => $country,
            'pno' => empty($birthDayStamp) ? '01011990' : $birthDayStamp,
            'gender' => $gender
        ];
    }
}
