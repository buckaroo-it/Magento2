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

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class SendCloudAddressHandler extends AbstractAddressHandler
{
    /**
     * Update shipping address by SendCloud
     *
     * @param Order                 $order
     * @param OrderAddressInterface $shippingAddress
     *
     * @return Order
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if (($order->getShippingMethod() == 'sendcloud_sendcloud')
            && $order->getSendcloudServicePointId()
        ) {
            $requestData = [];
            $this->updateShippingAddressBySendcloud($order, $requestData);
        }

        return $order;
    }

    /**
     * Set shipping address fields by SendCloud
     *
     * @param Order $order
     * @param array $requestData
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function updateShippingAddressBySendcloud(Order $order, array &$requestData)
    {
        if ($order->getSendcloudServicePointId() > 0) {
            $mapping = $this->getAddressMapping($order);
            foreach ($requestData as $key => $value) {
                if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                    $this->updateAddressData($requestData[$key], $mapping);
                }
            }
        }
    }

    /**
     * Get the mapping of address field names to Sendcloud service point values.
     *
     * @param Order $order
     * @return array
     */
    private function getAddressMapping(Order $order): array
    {
        return [
            ['Street', $order->getSendcloudServicePointStreet()],
            ['PostalCode', $order->getSendcloudServicePointZipCode()],
            ['City', $order->getSendcloudServicePointCity()],
            ['Country', $order->getSendcloudServicePointCountry()],
            ['StreetNumber', $order->getSendcloudServicePointHouseNumber()],
        ];
    }

    /**
     * Update a single address data entry with the mapped Sendcloud service point value.
     *
     * @param array $addressData
     * @param array $mapping
     * @return void
     */
    private function updateAddressData(array &$addressData, array $mapping): void
    {
        foreach ($mapping as $mappingItem) {
            if (($addressData['Name'] == $mappingItem[0]) && !empty($mappingItem[1])) {
                $addressData['_'] = $mappingItem[1];
            }
        }

        if ($addressData['Name'] == 'StreetNumberAdditional') {
            unset($addressData);
        }
    }
}
