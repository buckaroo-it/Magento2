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

namespace Buckaroo\Magento2\Model\PaypalExpress;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderAddressInterface;
use stdClass;

class OrderUpdateShipping
{
    /**
     * @var stdClass|null
     */
    protected $responseAddressInfo;

    /**
     * @var OrderAddressInterface
     */
    protected $shippingAddress;

    /**
     * @var BuckarooResponseDataInterface
     */
    private BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @param OrderAddressInterface $shippingAddress
     * @param BuckarooResponseDataInterface $buckarooResponseData
     */
    public function __construct(
        OrderAddressInterface $shippingAddress,
        BuckarooResponseDataInterface $buckarooResponseData
    ) {
        $this->shippingAddress = $shippingAddress;
        $this->buckarooResponseData = $buckarooResponseData;
        $this->responseAddressInfo = $this->getAddressInfoFromPayRequest();
    }

    /**
     * Get payment response
     *
     * @return array|null
     */
    private function getAddressInfoFromPayRequest(): ?array
    {
        $buckarooResponse = $this->buckarooResponseData->getResponse()->toArray();
        if (!empty($buckarooResponse)
            && isset($buckarooResponse['Services']['Service']['ResponseParameter'])
        ) {
            return $this->formatAddressData($buckarooResponse['Services']['Service']['ResponseParameter']);
        }

        return null;
    }

    /**
     * Format address data in key/value pairs
     *
     * @param mixed $addressData
     * @return array
     */
    public function formatAddressData($addressData): array
    {
        $data = [];
        if (!is_array($addressData)) {
            return $data;
        }

        foreach ($addressData as $addressItem) {
            if (isset($addressItem->_) && isset($addressItem->Name)) {
                $data[$addressItem->Name] = $addressItem->_;
            }
        }
        return $data;
    }

    /**
     * Update order shipping address with pay response data
     *
     * @return OrderAddressInterface
     */
    public function update(): OrderAddressInterface
    {
        $this->updateShippingItem(OrderAddressInterface::FIRSTNAME, 'payerFirstname');
        $this->updateShippingItem(OrderAddressInterface::LASTNAME, 'payerLastname');
        $this->updateShippingItem(OrderAddressInterface::STREET, 'address_line_1');
        $this->updateShippingItem(OrderAddressInterface::EMAIL, 'payerEmail');
        return $this->shippingAddress;
    }

    /**
     * Update shipping address field
     *
     * @param string|array $addressField
     * @param mixed $responseField
     * @return void
     */
    protected function updateShippingItem($addressField, $responseField)
    {
        if (isset($this->responseAddressInfo[$responseField])) {
            $this->shippingAddress->setData(
                $addressField,
                $this->responseAddressInfo[$responseField]
            );
        }
    }
}
