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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;

class DHLParcelAddressHandler extends AbstractAddressHandler
{
    /**
     * @var Curl
     */
    private $curl;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param Curl                    $curl
     */
    public function __construct(BuckarooLoggerInterface $logger, Curl $curl)
    {
        $this->curl = $curl;
        parent::__construct($logger);
    }

    /**
     * Update shipping address by DHL parcel service point
     *
     * @param Order                 $order
     * @param OrderAddressInterface $shippingAddress
     *
     * @return Order
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if (($order->getShippingMethod() == 'dhlparcel_servicepoint')
            && $order->getDhlparcelShippingServicepointId()
        ) {
            $this->updateShippingAddressByDhlParcel($order->getDhlparcelShippingServicepointId(), $shippingAddress);
        }

        return $order;
    }

    /**
     * Set shipping address fields by DHL Parcel
     *
     * @param string                $servicePointId
     * @param OrderAddressInterface $shippingAddress
     *
     * @return Address|null
     */
    public function updateShippingAddressByDhlParcel(string $servicePointId, OrderAddressInterface $shippingAddress)
    {
        $matches = [];
        if (preg_match('/^(.*)-([A-Z]{2})-(.*)$/', $servicePointId, $matches)) {
            $this->curl->get(
                'https://api-gw.dhlparcel.nl/parcel-shop-locations/'
                . $matches[2]
                . '/'
                . $servicePointId
            );
            if (($response = $this->curl->getBody())
                &&
                //phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                ($parsedResponse = @json_decode($response))
                &&
                !empty($parsedResponse->address)
            ) {
                $shippingAddress->setStreet([
                    $this->cleanStreetNumberAddition($parsedResponse->address->{'street'}),
                    property_exists($parsedResponse->address, 'number') ? $parsedResponse->address->{'number'} : '',
                    property_exists($parsedResponse->address, 'addition') ? $parsedResponse->address->{'addition'} : '',
                ]);
                $shippingAddress->setPostcode($parsedResponse->address->{'postalCode'});
                $shippingAddress->setCity($parsedResponse->address->{'city'});
                $shippingAddress->setCountryId($parsedResponse->address->{'countryCode'});
            }
        }

        return $shippingAddress;
    }

    /**
     * Remove all non-word characters
     *
     * @param string $addition
     *
     * @return array|string|string[]|null
     */
    private function cleanStreetNumberAddition($addition)
    {
        return preg_replace('/[\W]/', '', $addition);
    }
}
