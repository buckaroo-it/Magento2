<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Api\Data\OrderAddressInterface;

class PhoneDataBuilder extends AbstractDataBuilder
{
    private string $addressType;

    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $address = $this->getAddress();

        $telephone = $this->getPayment()->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $address->getTelephone() : $telephone);

        return ['phone' => [
            'mobile' => $telephone ?? '',
            'landline' => $telephone ?? ''
        ]];
    }

    private function getAddress()
    {
        return ($this->addressType == 'shipping')
            ? $this->getOrder()->getShippingAddress()
            : $this->getOrder()->getBillingAddress();
    }
}
