<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnain;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Magento\Sales\Model\Order\Address;

class ShippingAddressDataBuilder extends AbstractAddressDataBuilder
{
    /**
     * @var AddressHandlerPool
     */
    private AddressHandlerPool $addressHandlerPool;

    /**
     * @param AddressHandlerPool $addressHandlerPool
     */
    public function __construct(
        AddressHandlerPool $addressHandlerPool
    ) {
        $this->addressHandlerPool = $addressHandlerPool;
    }

    /**
     * Get Shipping Address
     *
     * @return Address
     * @throws \Exception
     */
    protected function getAddress(): Address
    {
        if (
            $this->isAddressDataDifferent($this->getPayment()) ||
            $this->getOrder()->getShippingAddress() === null ||
            $this->getPayment()->getMethod() === Klarna::CODE ||
            $this->getPayment()->getMethod() === Klarnain::CODE
        ) {
            return $this->addressHandlerPool->getShippingAddress($this->getOrder());
        } else {
            return $this->getOrder()->getShippingAddress();
        }
    }

    /**
     * Method to compare two addresses from the payment.
     *
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
     * Calculate differences between Billing and Shipping Address
     *
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
