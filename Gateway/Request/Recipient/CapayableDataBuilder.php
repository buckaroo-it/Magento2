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

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Service\Formatter\AddressFormatter;

class CapayableDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var AddressFormatter
     */
    private AddressFormatter $addressFormatter;

    /**
     * @param Data $helper
     * @param AddressFormatter $addressFormatter
     * @param string $addressType
     */
    public function __construct(
        AddressFormatter $addressFormatter,
        string $addressType = 'billing'
    ) {
        parent::__construct($addressType);
        $this->addressFormatter = $addressFormatter;
    }

    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        $category = $this->getCategory();

        $data = [
            'customerNumber' => $this->getCustomerNumber(),
            'category'       => $category,
            'initials'       => $this->getInitials(),
            'firstName'      => $this->getFirstname(),
            'lastName'       => $this->getLastName(),
            'careOf'         => $this->getCareOf(),
            'birthDate'      => $this->getBirthDate(),
            'phone'          => $this->getPhone(),
            'country'        => $this->getAddress()->getCountryId()
        ];

        if ($category == 'B2B') {
            $data['companyName'] = $this->getCompanyName();
            $data['chamberOfCommerce'] = $this->getChamberOfCommerce();
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function getCategory(): string
    {
        if (empty($this->getAddress()->getCompany())) {
            return 'B2C';
        }

        return 'B2B';
    }

    protected function getCustomerNumber()
    {
        $customerNumber = "guest";

        if (!empty($this->getOrder()->getCustomerId())) {
            $customerNumber = $this->getOrder()->getCustomerId();
        }

        return $customerNumber;
    }

    /**
     * Get phone
     *
     * @return string
     */
    protected function getPhone(): string
    {
        $phone = $this->getAddress()->getTelephone();

        if ($this->payment->getAdditionalInformation('customer_telephone') !== null) {
            $phone = $this->payment->getAdditionalInformation('customer_telephone');
        }

        $phoneData = $this->addressFormatter->formatTelephone($phone, $this->getAddress()->getCountryId());

        return $phoneData['clean'];
    }

    /**
     * Returns the date format used to format the customer's birthdate.
     *
     * @return string
     */
    protected function getFormatDate(): string
    {
        return 'Y-m-d';
    }
}
