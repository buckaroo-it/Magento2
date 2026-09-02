<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\Formatter\BirthDateFormatter;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AbstractRecipientDataBuilder extends AbstractDataBuilder
{
    /**
     * @var string
     */
    private $addressType;

    /**
     * @var BirthDateFormatter
     */
    protected BirthDateFormatter $birthDateFormatter;

    /**
     * @param BirthDateFormatter $birthDateFormatter
     * @param string             $addressType
     */
    public function __construct(BirthDateFormatter $birthDateFormatter, string $addressType = 'billing')
    {
        $this->birthDateFormatter = $birthDateFormatter;
        $this->addressType = $addressType;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['recipient' => $this->buildData()];
    }

    /**
     * Returns an array containing customer data
     *
     * @return array
     */
    protected function buildData(): array
    {
        return
            [
                'category'  => $this->getCategory(),
                'gender'    => $this->getGender(),
                'firstName' => $this->getFirstname(),
                'lastName'  => $this->getLastName(),
                'birthDate' => $this->getBirthDate()
            ];
    }

    /**
     * Returns the category of the customer.
     *
     * @return string
     */
    protected function getCategory(): string
    {
        return 'B2C';
    }

    /**
     * Returns the gender of the customer.
     *
     * @return string
     */
    protected function getGender(): string
    {
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return 'male';
        }
        return 'female';
    }

    /**
     * Returns the first name of the customer.
     *
     * @return string
     */
    protected function getFirstname(): string
    {
        return $this->getAddress()->getFirstname();
    }

    /**
     * Returns the address associated with the order.
     *
     * @return OrderAddressInterface
     */
    protected function getAddress(): OrderAddressInterface
    {
        if ($this->addressType == 'shipping') {
            return $this->getOrder()->getShippingAddress() ?? $this->getOrder()->getBillingAddress();
        } else {
            return $this->getOrder()->getBillingAddress();
        }
    }

    /**
     * Returns the last name of the customer.
     *
     * @return string
     */
    protected function getLastName(): string
    {
        return $this->getAddress()->getLastName();
    }

    /**
     * Returns the birthdate of the customer.
     *
     * Falls back to the placeholder date when the order carries no usable
     * birthdate, because the services built on this builder reject a missing
     * birthDate. Never returns null here; the type is nullable only so that
     * BillinkDataBuilder - whose service does accept a missing birthDate - can
     * narrow it.
     *
     * @return string|null
     */
    protected function getBirthDate(): ?string
    {
        return $this->birthDateFormatter->formatOrDefault(
            $this->getRawBirthDate(),
            $this->getFormatDate()
        );
    }

    /**
     * Returns the birthdate as entered, preferring the checkout DoB field over the value stored on the order.
     *
     * @return string|null
     */
    protected function getRawBirthDate(): ?string
    {
        $customerDoB = trim((string)$this->payment->getAdditionalInformation('customer_DoB'));

        if ($customerDoB !== '') {
            return $customerDoB;
        }

        return $this->getOrder()->getCustomerDob();
    }

    /**
     * Returns the date format used to format the customer's birthdate.
     *
     * @return string
     */
    protected function getFormatDate(): string
    {
        return 'd-m-Y';
    }

    /**
     * Returns whether the category of customer
     *
     * @return string
     */
    protected function getCareOf(): string
    {
        if (empty($this->getOrder()->getBillingAddress()->getCompany())) {
            return 'Person';
        }

        return 'Company';
    }

    /**
     * Returns the Chamber of Commerce number of the customer.
     *
     * @return mixed
     */
    protected function getChamberOfCommerce()
    {
        return $this->payment->getAdditionalInformation('customer_chamberOfCommerce');
    }

    /**
     * Required if Billing country is NL or BE. Possible values: Mr, Mrs, Miss.
     *
     * @return string
     */
    protected function getTitle(): string
    {
        if ($this->getGender() === 'male') {
            return 'Mr';
        }

        return 'Mrs';
    }

    /**
     * Returns the initials of the customer's first name.
     *
     * @return string
     */
    protected function getInitials(): string
    {
        return strtoupper(substr($this->getFirstname(), 0, 1));
    }

    /**
     * Get Company Name
     *
     * @return string
     */
    protected function getCompanyName(): string
    {
        return $this->payment->getAdditionalInformation('CompanyName') ?: $this->getAddress()->getCompany() ?: '';
    }
}
