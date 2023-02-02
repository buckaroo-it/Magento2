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

namespace Buckaroo\Magento2\Model\PaypalExpress;

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
     * @var \Magento\Sales\Api\Data\OrderAddressInterface
     */
    protected $shippingAddress;

    public function __construct(
        OrderAddressInterface $shippingAddress,
        Registry $registry
    ) {
        $this->shippingAddress = $shippingAddress;
        $this->responseAddressInfo = $this->getAddressInfoFromPayRequest($registry);
    }
    /**
     * Update order shipping address with pay response data
     *
     * @return \Magento\Sales\Api\Data\OrderAddressInterface
     */
    public function update()
    {
        $this->updateShippingItem(OrderAddressInterface::FIRSTNAME, 'payerFirstname');
        $this->updateShippingItem(OrderAddressInterface::LASTNAME, 'payerLastname');
        $this->updateShippingItem(OrderAddressInterface::STREET, 'address_line_1');
        $this->updateShippingItem(OrderAddressInterface::EMAIL, 'payerEmail');
        return $this->shippingAddress;
    }
    protected function updateShippingItem($addressField, $responseField)
    {
        if (isset($this->responseAddressInfo[$responseField])) {
            $this->shippingAddress->setData(
                $addressField,
                $this->responseAddressInfo[$responseField]
            );
        }
    }
    /**
     * Get payment response
     *
     * @param Registry $registry
     * @return stdClass|array|null
     */
    private function getAddressInfoFromPayRequest($registry)
    {
        if (
            $registry &&
            $registry->registry("buckaroo_response") &&
            isset($registry->registry("buckaroo_response")[0]) &&
            isset($registry->registry("buckaroo_response")[0]->Services->Service->ResponseParameter)
        ) {
            return $this->formatAddressData(
                $registry->registry("buckaroo_response")[0]->Services->Service->ResponseParameter
            );
        }

        return null;
    }
    /**
     * Format address data in key/value pairs
     *
     * @param mixed $addressData
     *
     * @return array
     */
    public function formatAddressData($addressData)
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
}
