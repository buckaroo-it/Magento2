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

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Model\Service\Order;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class SendCloudAddressHandler extends AbstractAddressHandler
{
    /**
     * Update shipping address by SendCloud
     *
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
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
     * @return void
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
