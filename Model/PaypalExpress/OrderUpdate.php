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
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;

class OrderUpdate
{
    /**
     * @var stdClass|null
     */
    protected $responseAddressInfo;

    /**
     * @var BuckarooResponseDataInterface
     */
    private BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @param BuckarooResponseDataInterface $buckarooResponseData
     */
    public function __construct(
        BuckarooResponseDataInterface $buckarooResponseData
    ) {
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
     * Update order address with pay response data
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface
     * @return \Magento\Sales\Api\Data\OrderAddressInterface
     */
    public function updateAddress($address)
    {
        $this->updateItem($address, OrderAddressInterface::FIRSTNAME, 'payerFirstname');
        $this->updateItem($address, OrderAddressInterface::LASTNAME, 'payerLastname');
        $this->updateItem($address, OrderAddressInterface::STREET, 'address_line_1');
        $this->updateItem($address, OrderAddressInterface::EMAIL, 'payerEmail');
        return $address;
    }

    protected function updateItem($address, $addressField, $responseField)
    {
        if ($this->valueExists($responseField)) {
            $address->setData(
                $addressField,
                $this->responseAddressInfo[$responseField]
            );
        }
    }

    private function valueExists($key): bool
    {
        return isset($this->responseAddressInfo[$key]) && is_string($this->responseAddressInfo[$key]);
    }

    /**
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    public function updateEmail(OrderInterface $order)
    {
        if ($this->valueExists('payerEmail')) {
            $order->setCustomerEmail($this->responseAddressInfo['payerEmail']);
        };
    }
}
