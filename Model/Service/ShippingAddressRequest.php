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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;

class ShippingAddressRequest implements ShippingAddressRequestInterface
{
    protected $city;

    protected $country_code;

    protected $postal_code;

    protected $state;

    /**
     * @inheritDoc
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @inheritDoc
     */
    public function setCity(string $city)
    {
        $this->validateRequired($city, 'city');
        $this->city = $city;
    }

    /**
     * Validate required fields
     *
     * @param mixed $value
     * @param string $name
     *
     * @return void
     * @throws \Buckaroo\Magento2\Model\Service\ExpressMethodsException
     */
    protected function validateRequired($value, $name)
    {
        if (strlen(trim($value)) === 0) {
            throw new ExpressMethodsException("Parameter `{$name}` is required");
        }
    }

    /**
     * @inheritDoc
     */
    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    /**
     * @inheritDoc
     */
    public function setCountryCode(string $country_code)
    {
        $this->validateRequired($country_code, 'country_code');
        $this->country_code = $country_code;
    }

    /**
     * @inheritDoc
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * @inheritDoc
     */
    public function setPostalCode(string $postalCode)
    {
        $this->validateRequired($postalCode, 'postal_code');
        $this->postalCode = $postalCode;
    }

    /**
     * @inheritDoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function setState(string $state)
    {
        $this->state = $state;
    }
}
