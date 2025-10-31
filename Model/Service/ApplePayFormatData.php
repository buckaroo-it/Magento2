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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;

class ApplePayFormatData implements FormatFormDataInterface
{
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ShippingAddressRequestInterfaceFactory
     */
    private $shippingAddrRequestFactory;

    /**
     * @param DataObjectFactory                      $dataObjectFactory
     * @param ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
     */
    public function __construct(
        DataObjectFactory $dataObjectFactory,
        ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingAddrRequestFactory = $shippingAddrRequestFactory;
    }

    /**
     * Get Product Object By Request
     *
     * @param  array               $productData
     * @throws AddProductException
     * @return DataObject
     */
    public function getProductObject(array $productData): DataObject
    {
        if (!isset($productData['id'])) {
            throw new AddProductException("A product is required", 1);
        }

        return $this->dataObjectFactory->create(
            [
                'data' => [
                    'product'                      => $productData['id'],
                    'selected_configurable_option' => '',
                    'related_product'              => '',
                    'item'                         => $productData['id'],
                    'super_attribute'              => $productData['selected_options'] ?? '',
                    'qty'                          => $productData['qty'],
                ]
            ]
        );
    }

    /**
     * Get Product Object By Request
     *
     * @param  array                           $addressData
     * @throws ExpressMethodsException
     * @return ShippingAddressRequestInterface
     */
    public function getShippingAddressObject(array $addressData): ShippingAddressRequestInterface
    {
        $shippingAddressRequest = $this->shippingAddrRequestFactory->create();

        $shippingAddressRequest->setCountryCode(
            isset($addressData['countryCode']) ? strtoupper($addressData['countryCode']) : 'NL'
        );

        $shippingAddressRequest->setPostalCode($addressData['postalCode'] ?? '');
        $shippingAddressRequest->setCity($addressData['locality'] ?? '');
        $shippingAddressRequest->setState(isset($addressData['administrativeArea']) && $addressData['administrativeArea']
            ? $addressData['administrativeArea']
            : 'unknown');

        return $shippingAddressRequest;
    }
}
