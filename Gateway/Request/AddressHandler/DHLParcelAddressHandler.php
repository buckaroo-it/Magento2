<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

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

    public function handle(Order $order): Order
    {
        if (($order->getShippingMethod() == 'dhlparcel_servicepoint')
            && $order->getDhlparcelShippingServicepointId()) {
            $this->updateShippingAddressByDhlParcel(
                $order->getDhlparcelShippingServicepointId(), $requestData
            );
        }

        return $order;
    }

    public function updateShippingAddressByDhlParcel($servicePointId, &$requestData)
    {
        $this->buckarooLogger->addDebug(__METHOD__ . '|1|');

        $matches = [];
        if (preg_match('/^(.*)-([A-Z]{2})-(.*)$/', $servicePointId, $matches)) {
            $this->curl = $this->objectManager->get('Magento\Framework\HTTP\Client\Curl');
            $this->curl->get('https://api-gw.dhlparcel.nl/parcel-shop-locations/' . $matches[2] . '/' . $servicePointId);
            if (($response = $this->curl->getBody())
                &&
                //phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                ($parsedResponse = @json_decode($response))
                &&
                !empty($parsedResponse->address)
            ) {
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                        $mapping = [
                            ['Street', 'street'],
                            ['PostalCode', 'postalCode'],
                            ['City', 'city'],
                            ['Country', 'countryCode'],
                            ['StreetNumber', 'number'],
                            ['StreetNumberAdditional', 'addition'],
                        ];
                        foreach ($mapping as $mappingItem) {
                            if (($requestData[$key]['Name'] == $mappingItem[0]) && (!empty($parsedResponse->address->{$mappingItem[1]}))) {
                                if ($mappingItem[1] == 'addition') {
                                    $parsedResponse->address->{$mappingItem[1]} =
                                        $this->cleanStreetNumberAddition($parsedResponse->address->{$mappingItem[1]});
                                }
                                $requestData[$key]['_'] = $parsedResponse->address->{$mappingItem[1]};
                            }
                        }

                    }
                }
            }
        }
    }

    private function cleanStreetNumberAddition($addition)
    {
        return preg_replace('/[\W]/', '', $addition);
    }


}
