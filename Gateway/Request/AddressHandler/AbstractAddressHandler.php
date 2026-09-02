<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AddressHandler;

use Buckaroo\Magento2\Api\AddressHandlerInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

/**
 * The default chaining behavior can be implemented inside a base handler class.
 */
abstract class AbstractAddressHandler implements AddressHandlerInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooLoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Update shipping address specific mapping
     *
     * @param array $mapping
     * @param array $requestData
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
                        $found = true;
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_' => $mappingItem[1],
                        'Name' => $mappingItem[0],
                    ];
                }
            }
        }
    }

    /**
     * Update shipping address specific mapping
     *
     * @param array $mapping
     * @param array $requestData
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function updateShippingAddressCommonMapping(array $mapping, array &$requestData)
    {
        foreach ($mapping as $mappingItem) {
            if (!empty($mappingItem[1])) {
                $found = false;
                foreach ($requestData as $key => $value) {
                    if ($requestData[$key]['Group'] == 'ShippingCustomer'
                        && $requestData[$key]['Name'] == $mappingItem[0]) {
                        $requestData[$key]['_'] = $mappingItem[1];
                        $found = true;
                    }
                }
                if (!$found) {
                    $requestData[] = [
                        '_' => $mappingItem[1],
                        'Name' => $mappingItem[0],
                        'Group' => 'ShippingCustomer',
                        'GroupID' => '',
                    ];
                }
            }
        }
    }
}
