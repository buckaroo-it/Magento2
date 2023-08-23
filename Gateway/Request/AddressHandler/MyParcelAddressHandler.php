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

use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class MyParcelAddressHandler extends AbstractAddressHandler
{
    /**
     * @var BuckarooHelper
     */
    public BuckarooHelper $helper;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param BuckarooHelper $helper
     */
    public function __construct(BuckarooLoggerInterface $logger, BuckarooHelper $helper)
    {
        $this->helper = $helper;
        parent::__construct($logger);
    }

    /**
     * Update Shipping Address By MyParcel
     *
     * @param Order $order
     * @param OrderAddressInterface $shippingAddress
     * @return Order
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        $myparcelFetched = false;
        $myparcelOptions = $order->getData('myparcel_delivery_options');
        $requestData = $shippingAddress->getData();
        if (!empty($myparcelOptions)) {
            try {
                $myparcelOptions = json_decode($myparcelOptions, true);
                $isPickup = $myparcelOptions['isPickup'] ?? false;
                if ($isPickup) {
                    $this->updateShippingAddressByMyParcel(
                        $myparcelOptions['pickupLocation'],
                        $requestData
                    );
                    $myparcelFetched = true;
                }
            } catch (\JsonException $je) {
                $this->logger->addError(sprintf(
                    '[CREATE_ORDER] | [Gateway] | [%s:%s] - Error related to json_decode' . '
                    (MyParcel plugin compatibility) | [ERROR]: %s',
                    __METHOD__, __LINE__,
                    $je->getMessage()
                ));
            }
        }

        if (!$myparcelFetched) {
            if ((strpos((string)$order->getShippingMethod(), 'myparcelnl') !== false)
                && (strpos((string)$order->getShippingMethod(), 'pickup') !== false)
            ) {
                if ($this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()
                    && $myParcelNLData = $this->helper->getJson()->unserialize(
                        $this->helper->getCheckoutSession()->getMyParcelNLBuckarooData()
                    )
                ) {
                    $this->updateShippingAddressByMyParcel($myParcelNLData, $requestData);
                }
            }
        }


        return $order;
    }

    /**
     * Update shipping address by DPD Pickup point
     *
     * @param array $myParcelLocation
     * @param array $requestData
     * @return void
     */
    protected function updateShippingAddressByMyParcel(array $myParcelLocation, array &$requestData)
    {
        $mapping = [
            ['ShippingStreet', $myParcelLocation['street']],
            ['ShippingPostalCode', $myParcelLocation['postal_code']],
            ['ShippingCity', $myParcelLocation['city']],
            ['ShippingCountryCode', $myParcelLocation['cc']],
            ['ShippingHouseNumber', $myParcelLocation['number']],
            ['ShippingHouseNumberSuffix', $myParcelLocation['number_suffix']],
        ];

        $this->logger->addDebug(sprintf(
            '[CREATE_ORDER] | [Gateway] | [%s:%s] - Set shipping address fields by myParcelNL | newAddress: %s',
            __METHOD__, __LINE__,
            var_export($mapping, true)
        ));

        $this->updateShippingAddressCommonMappingV2($mapping, $requestData);
    }

    /**
     * Set shipping address fields by DPD Parcel
     *
     * @param array $myParcelLocation
     * @param array $requestData
     * @return void
     */
    protected function updateShippingAddressByMyParcelV2(array $myParcelLocation, array &$requestData)
    {
        $mapping = [
            ['Street', $myParcelLocation['street']],
            ['PostalCode', $myParcelLocation['postal_code']],
            ['City', $myParcelLocation['city']],
            ['Country', $myParcelLocation['cc']],
            ['StreetNumber', $myParcelLocation['number']],
            ['StreetNumberAdditional', $myParcelLocation['number_suffix']],
        ];

        $this->logger->addDebug(sprintf(
            '[CREATE_ORDER] | [Gateway] | [%s:%s] - Set shipping address fields by myParcelNL V2 | newAddress: %s',
            __METHOD__, __LINE__,
            var_export($mapping, true)
        ));

        $this->updateShippingAddressCommonMapping($mapping, $requestData);
    }
}
