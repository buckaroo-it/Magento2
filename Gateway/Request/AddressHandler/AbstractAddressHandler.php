<?php

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Api\AddressHandlerInterface;
use Buckaroo\Magento2\Logging\Log;

/**
 * The default chaining behavior can be implemented inside a base handler class.
 */
abstract class AbstractAddressHandler implements AddressHandlerInterface
{
    protected Log $buckarooLogger;

    public function __construct(
        Log $buckarooLogger
    ) {
        $this->buckarooLogger = $buckarooLogger;
    }

    /**
     * @param array $mapping
     * @param array $requestData
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function updateShippingAddressCommonMappingV2(array $mapping, array &$requestData)
    {
        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Name'] == $mappingItem[0]) {
                        $requestData[$key]['_'] = $mappingItem[1];
                        $found                  = true;
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_'    => $mappingItem[1],
                        'Name' => $mappingItem[0],
                    ];
                }
            }
        }
    }

    /**
     * @param array $mapping
     * @param array $requestData
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function updateShippingAddressCommonMapping(array $mapping, array &$requestData)
    {
        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Group'] == 'ShippingCustomer') {
                        if ($requestData[$key]['Name'] == $mappingItem[0]) {
                            $requestData[$key]['_'] = $mappingItem[1];
                            $found                  = true;
                        }
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_'       => $mappingItem[1],
                        'Name'    => $mappingItem[0],
                        'Group'   => 'ShippingCustomer',
                        'GroupID' => '',
                    ];
                }
            }
        }
    }
}
