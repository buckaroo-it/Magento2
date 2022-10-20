<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class CountryDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $this->getOrder()->getBillingAddress();

        return [
            'country' => $billingAddress->getCountryId(),
        ];
    }
}
