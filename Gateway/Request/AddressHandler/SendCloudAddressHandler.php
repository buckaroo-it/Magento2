<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class SendCloudAddressHandler extends AbstractAddressHandler
{
    /**
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
     * @return Order
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if (($order->getShippingMethod() == 'sendcloud_sendcloud')
            &&
            $order->getSendcloudServicePointId()
        ) {
            $this->updateShippingAddressBySendcloud($order, $requestData);
        }

        return $order;
    }

    /**
     * @param $order
     * @param $requestData
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function updateShippingAddressBySendcloud($order, &$requestData)
    {
        if ($order->getSendcloudServicePointId() > 0) {
            foreach ($requestData as $key => $value) {
                if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                    $mapping = [
                        ['Street', $order->getSendcloudServicePointStreet()],
                        ['PostalCode', $order->getSendcloudServicePointZipCode()],
                        ['City', $order->getSendcloudServicePointCity()],
                        ['Country', $order->getSendcloudServicePointCountry()],
                        ['StreetNumber', $order->getSendcloudServicePointHouseNumber()],
                    ];
                    foreach ($mapping as $mappingItem) {
                        if (($requestData[$key]['Name'] == $mappingItem[0]) && !empty($mappingItem[1])) {
                            $requestData[$key]['_'] = $mappingItem[1];
                        }
                    }

                    if ($requestData[$key]['Name'] == 'StreetNumberAdditional') {
                        unset($requestData[$key]);
                    }
                }
            }
        }
    }
}
