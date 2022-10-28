<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Resources\Constants\Gender;
use Magento\Sales\Api\Data\OrderAddressInterface;

class CustomerNameDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $this->getOrder()->getBillingAddress();

        return [
            'customer' => [
                'firstName' => $billingAddress->getFirstname(),
                'lastName' => $billingAddress->getLastname(),
            ]

        ];
    }
}
