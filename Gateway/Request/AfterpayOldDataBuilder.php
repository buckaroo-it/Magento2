<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Resources\Constants\RecipientCategory;
use Buckaroo\Magento2\Gateway\Request\Recipient\AfterpayOldDataBuilder as RecipientAfterpayOld;

class AfterpayOldDataBuilder extends AbstractDataBuilder
{
    private RecipientAfterpayOld $recipientAfterpay;
    private ClientIPDataBuilder $clientIPDataBuilder;

    public function __construct(RecipientAfterpayOld $recipientAfterpay, ClientIPDataBuilder $clientIPDataBuilder)
    {
        $this->recipientAfterpay = $recipientAfterpay;
        $this->clientIPDataBuilder = $clientIPDataBuilder;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $category = $this->recipientAfterpay->getCategory($this->getOrder(), $this->getPayment());
        $accept = 'false';
        if ($this->getPayment()->getAdditionalInformation('termsCondition')) {
            $accept = 'true';
        }

        return [
            'customerIPAddress' => $this->clientIPDataBuilder->getIp($this->getOrder()),
            'addressesDiffer' => $this->isAddressDataDifferent(),
            'b2b' => $category == RecipientCategory::COMPANY,
            'accept' => $accept
        ];
    }

    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @return boolean
     */
    public function isAddressDataDifferent(): bool
    {
        $billingAddress  = $this->getOrder()->getBillingAddress();
        $shippingAddress = $this->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData  = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

        return !empty($arrayDifferences);
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return array
     */
    private function calculateAddressDataDifference(array $addressOne, array $addressTwo): array
    {
        $keysToExclude = array_flip([
            'prefix',
            'telephone',
            'fax',
            'created_at',
            'email',
            'customer_address_id',
            'vat_request_success',
            'vat_request_date',
            'vat_request_id',
            'vat_is_valid',
            'vat_id',
            'address_type',
            'extension_attributes',
            'quote_address_id'
        ]);

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        return array_diff($filteredAddressOne, $filteredAddressTwo);
    }
}
