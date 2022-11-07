<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AbstractRecipientDataBuilder extends AbstractDataBuilder
{
    private string $addressType;

    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['recipient' => $this->buildData()];
    }

    protected function buildData(): array
    {
        return
            [
                'category' => $this->getCategory(),
                'gender' => $this->getGender(),
                'firstName' => $this->getFirstname(),
                'lastName' => $this->getLastName(),
                'birthDate' => $this->getBirthDate()
            ];
    }

    protected function getAddress(): OrderAddressInterface
    {
        if ($this->addressType == 'shipping') {
            return $this->getOrder()->getShippingAddress();
        } else {
            return $this->getOrder()->getBillingAddress();
        }
    }

    protected function getFirstname(): string
    {
        return $this->getAddress()->getFirstname();
    }

    protected function getLastName(): string
    {
        return $this->getAddress()->getLastName();
    }

    protected function getCategory(): string
    {
        return 'B2C';
    }

    protected function getGender(): string
    {
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return 'male';
        }
        return 'female';
    }

    protected function getCareOf(): string
    {
        if (empty($this->getOrder()->getBillingAddress()->getCompany())) {
            return 'Person';
        }

        return 'Company';
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

    protected function getChamberOfCommerce()
    {
        return $this->payment->getAdditionalInformation('customer_chamberOfCommerce');
    }

    /**
     * Required if Billing country is NL or BE.
     * Possible values: Mr, Mrs, Miss.
     * @return string
     */
    protected function getTitle(): string
    {
        if ($this->getGender() === 'male') {
            return 'Mr';
        }

        return 'Mrs';
    }

    protected function getInitials(): string
    {
        return strtoupper(substr($this->getFirstname(), 0, 1));
    }
}