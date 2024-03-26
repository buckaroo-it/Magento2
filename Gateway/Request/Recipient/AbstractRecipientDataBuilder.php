<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AbstractRecipientDataBuilder extends AbstractDataBuilder
{
    /**
     * @var string
     */
    private string $addressType;

    /**
     * @param string $addressType
     */
    public function __construct(string $addressType = 'billing')
    {
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
     * Returns the birthdate of the customer
     *
     * @return false|string
     */
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
