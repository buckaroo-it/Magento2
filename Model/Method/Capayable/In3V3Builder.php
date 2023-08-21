<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Method\Capayable;

use Magento\Sales\Model\Order\Address;
use Magento\Sales\Api\Data\OrderItemInterface;
use Buckaroo\Magento2\Service\Formatter\AddressFormatter;

class In3V3Builder
{
    /** @var AddressFormatter */
    public $addressFormatter;
    
    public function __construct(
        AddressFormatter $addressFormatter
    ) {
        $this->addressFormatter = $addressFormatter;
    }



    public function build($payment) {
        return [
            'Name'   => 'In3',
            'Action' => 'Pay',
            'RequestParameter' => array_merge(
                $this->getProducts($payment->getOrder()->getAllItems()),
                $this->getBillingCustomer(
                    $payment->getOrder()->getBillingAddress(),
                    $this->getBirthDate($payment),
                    $this->getPhone($payment->getOrder()->getBillingAddress() ,$payment)
                ),
                $this->getShippingAddress($payment->getOrder()->getShippingAddress())
            )
        ];
    }

    /**
     * Get formated shipping address
     *
     * @param Address $billingAddress
     *
     * @return void
     */
    protected function getShippingAddress(
        Address $shippingAddress,
    ): array
    {
        $i = 1;

        $streetData = $this->addressFormatter->formatStreet($shippingAddress->getStreet());

        $data = [
            $this->row($streetData['street'], 'Street', 'BillingCustomer', $i),
            $this->row($streetData['house_number'], 'StreetNumber', 'BillingCustomer', $i),
            $this->row($shippingAddress->getPostcode(), 'PostalCode', 'BillingCustomer', $i),
            $this->row($shippingAddress->getCity(), 'City', 'BillingCustomer', $i),
            $this->row($shippingAddress->getCountryId(), 'CountryCode', 'BillingCustomer', $i),
        ];

        if (strlen($streetData['number_addition']) > 0) {
            $data[] = $this->row($streetData['number_addition'], 'StreetNumberSuffix', 'BillingCustomer', $i);
        }

        if(strlen((string)$shippingAddress->getRegion())) {
            $data[] = $this->row($shippingAddress->getRegion(), 'Region', 'BillingCustomer', $i);
        }

        return $data;
    }

    /**
     * Get formated billing customer
     *
     * @param Address $billingAddress
     *
     * @return void
     */
    protected function getBillingCustomer(
        Address $billingAddress,
        string $birthDate,
        string $phone
    ): array
    {
        $i = 1;

        $streetData = $this->addressFormatter->formatStreet($billingAddress->getStreet());

        $customerNumber = "guest";

        if($billingAddress->getEntityId() !== null) {
            $customerNumber = $billingAddress->getEntityId();
        }
        $data = [
            $this->row((string)$customerNumber, 'CustomerNumber', 'BillingCustomer', $i),
            $this->row($billingAddress->getFirstname(), 'FirstName', 'BillingCustomer', $i),
            $this->row($billingAddress->getLastname(), 'LastName', 'BillingCustomer', $i),
            $this->row($this->getInitials($billingAddress), 'Initials', 'BillingCustomer', $i),
            $this->row($birthDate, 'BirthDate', 'BillingCustomer', $i),
            $this->row($phone, 'Phone', 'BillingCustomer', $i),
            $this->row($billingAddress->getEmail(), 'Email', 'BillingCustomer', $i),
            $this->row("B2C", 'Category', 'BillingCustomer', $i),
            
            $this->row($streetData['street'], 'Street', 'BillingCustomer', $i),
            $this->row($streetData['house_number'], 'StreetNumber', 'BillingCustomer', $i),
            $this->row($billingAddress->getPostcode(), 'PostalCode', 'BillingCustomer', $i),
            $this->row($billingAddress->getCity(), 'City', 'BillingCustomer', $i),
            $this->row($billingAddress->getCountryId(), 'CountryCode', 'BillingCustomer', $i),
        ];

        if (strlen($streetData['number_addition']) > 0) {
            $data[] = $this->row($streetData['number_addition'], 'StreetNumberSuffix', 'BillingCustomer', $i);
        }

        if(strlen($billingAddress->getRegion())) {
            $data[] = $this->row($billingAddress->getRegion(), 'Region', 'BillingCustomer', $i);
        }

        return $data;
    }

    /**
     * Get formated order product lines
     *
     * @param array $orderItems
     *
     * @return void
     */
    protected function getProducts(array $orderItems)
    {
        $productData = [];
        $max = 99;
        $i = 1;

        /** @var OrderItemInterface $item */
        foreach ($orderItems as $item) {
            if (empty($item) || $item->getParentItem() !== null) {
                continue;
            }

            $productData = [
                $this->row($item->getSku(), 'Identifier', 'Article', $i),
                $this->row($item->getName(), 'Description', 'Article', $i),
                $this->row($item->getQtyOrdered(), 'Quantity', 'Article', $i),
                $this->row($item->getBasePriceInclTax(), 'GrossUnitPrice', 'Article', $i),
                $this->row($item->getTaxPercent(), 'VatPercentage', 'Article', $i)
            ];

            $i++;

            if ($i > $max) {
                break;
            }
        }

        return $productData;
    }

    /**
     * Get phone
     *
     * @param Address $address
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return string
     */
    private function getPhone(Address $address, $payment) {
        $phone = $address->getTelephone();

        if ($payment->getAdditionalInformation('customer_telephone') !== null) {
            $phone = $payment->getAdditionalInformation('customer_telephone');
        }
        
        $phoneData = $this->addressFormatter->formatTelephone(
            $phone,
            $address->getCountryId()
        );

        return $phoneData['clean'];
    }

    /**
     * Get birth date
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return string|null
     */
    private function getBirthDate($payment) {
        $birthDate = $payment->getAdditionalInformation('customer_DoB');
        if(!is_string($birthDate)) {
            return null;
        }

        return $birthDate;
    }

    /**
     * Get initials
     *
     * @param Address $address
     *
     * @return string
     */
    private function getInitials(Address $address): string
    {
        return  substr($address->getFirstname(), 0, 1) . substr($address->getLastname(), 0, 1);
    }

    /**
     * Get row
     *
     * @param mixed $value
     * @param string $name
     * @param string|int|null $groupType
     * @param string|int|null $groupId
     *
     * @return void
     */
    private function row($value, $name, $groupType = null, $groupId = null)
    {
        $row = [
            '_' => $value,
            'Name' => $name
        ];

        if ($groupType !== null) {
            $row['Group'] = $groupType;
        }

        if ($groupId !== null) {
            $row['GroupID'] = $groupId;
        }

        return $row;
    }
}
