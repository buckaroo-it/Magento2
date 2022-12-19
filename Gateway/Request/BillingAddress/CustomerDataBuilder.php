<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BillingAddress;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;

class CustomerDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        /** @var OrderAddressInterface $billingAddress */
        $billingAddress = $this->getOrder()->getBillingAddress();

        return [
            'customer' => [
                'firstName' => $billingAddress->getFirstname(),
                'lastName' => $billingAddress->getLastname(),
                'initials' => strtoupper(substr($billingAddress->getFirstname(), 0, 1)),
                'birthDate' => $this->getBirthDate()
            ]
        ];
    }

    protected function getBirthDate()
    {
        $customerDoB = (string)$this->payment->getAdditionalInformation('customer_DoB');
        if (empty($customerDoB)) {
            $customerDoB = $this->getOrder()->getCustomerDob() ?? '1990-01-01';
        }

        return date(
            $this->getFormatDate(),
            strtotime(str_replace('/', '-', $customerDoB))
        );
    }

    protected function getFormatDate(): string
    {
        return 'd-m-Y';
    }
}
