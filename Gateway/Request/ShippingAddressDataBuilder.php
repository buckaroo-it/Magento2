<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\Method\Klarna\Klarnain;
use Buckaroo\Magento2\Plugin\Method\Klarna;
use Magento\Sales\Model\Order\Address;

class ShippingAddressDataBuilder extends AbstractAddressDataBuilder
{
    private AddressHandlerPool $addressHandlerPool;

    public function __construct(
        AddressHandlerPool $addressHandlerPool
    ) {
        $this->addressHandlerPool = $addressHandlerPool;
    }

    /**
     * @return Address
     * @throws \Exception
     */
    protected function getAddress(): Address
    {
        if (
            $this->isAddressDataDifferent($this->getPayment()) ||
            is_null($this->getOrder()->getShippingAddress()) ||
            $this->getPayment()->getMethod() === Klarna::KLARNA_METHOD_NAME ||
            $this->getPayment()->getMethod() === Klarnain::PAYMENT_METHOD_CODE
        ) {
            return $this->addressHandlerPool->getShippingAddress($this->getOrder());
        } else {
            return $this->getOrder()->getShippingAddress();
        }
    }

    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return boolean
     */
    public function isAddressDataDifferent($payment): bool
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData = $billingAddress->getData();
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

