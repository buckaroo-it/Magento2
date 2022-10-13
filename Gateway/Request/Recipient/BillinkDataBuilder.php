<?php

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;
use Buckaroo\Magento2\Helper\Data;

class BillinkDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var Data
     */
    public Data $helper;

    public function __construct(Data $helper, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->helper = $helper;
    }

    protected function buildData(): array
    {
        $category = $this->getCategory();

        $data = [
            'category' => $category,
            'careOf' => $this->getCareOf(),
            'title' => $this->getGender(),
            'initials' => $this->getInitials(),
            'firstName' => $this->getFirstname(),
            'lastName' => $this->getLastName(),
            'birthDate' => $this->getBirthDate()
        ];

        if($category == 'B2B') {
            $data['chamberOfCommerce'] = $this->getChamberOfCommerce();
        }

        return $data;
    }

    protected function getCategory(): string
    {
        return $this->helper->checkCustomerGroup('buckaroo_magento2_billink') ? 'B2B' : 'B2C';
    }

    protected function getCareOf(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastName();
    }

    protected function getGender(): string
    {
        return ucfirst(parent::getGender());
    }

    protected function getInitials(): string
    {
        return strtoupper(substr($this->getFirstname(), 0, 1));
    }
}
