<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Model\Order;
use Magento\Framework\HTTP\Client\Curl;

class DHLParcelAddressHandler extends AbstractAddressHandler
{
    private Curl $curl;

    public function __construct(Log $buckarooLogger, Curl $curl)
    {
        $this->curl = $curl;
        parent::__construct($buckarooLogger);
    }

    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order
    {
        if (
            ($order->getShippingMethod() == 'dhlparcel_servicepoint')
            && $order->getDhlparcelShippingServicepointId()
        ) {
            $this->updateShippingAddressByDhlParcel($order->getDhlparcelShippingServicepointId(), $shippingAddress);
        }

        return $order;
    }

    /**
     * @param string $servicePointId
     * @return \Magento\Sales\Model\Order\Address|null
     */
    public function updateShippingAddressByDhlParcel(string $servicePointId, $shippingAddress)
    {
        $this->buckarooLogger->addDebug(__METHOD__ . '|1|');
        $matches = [];
        if (preg_match('/^(.*)-([A-Z]{2})-(.*)$/', $servicePointId, $matches)) {
            $this->curl->get('https://api-gw.dhlparcel.nl/parcel-shop-locations/' . $matches[2] . '/' . $servicePointId);
            if (
                ($response = $this->curl->getBody())
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

    private function cleanStreetNumberAddition($addition)
    {
        return preg_replace('/[\W]/', '', $addition);
    }
}
