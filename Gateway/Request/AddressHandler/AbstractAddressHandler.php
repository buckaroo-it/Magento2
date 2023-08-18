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
    protected BuckarooLoggerInterface $logger;

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
