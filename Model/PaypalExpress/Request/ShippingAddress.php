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

namespace Buckaroo\Magento2\Model\PaypalExpress\Request;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;

class ShippingAddress implements ShippingAddressRequestInterface
{

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $country_code;

    /**
     * @var string
     */
    protected $postal_code;

    /**
     * @var string
     */
    protected $state;

    /**
     * @inheritDoc
     */
    public function setCity(string $city)
    {
        $this->validateRequired($city, 'city');
        $this->city = $city;
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
    public function setPostalCode(string $postal_code)
    {
        $this->validateRequired($postal_code, 'postal_code');
        $this->postal_code = $postal_code;
    }

    /**
     * @inheritDoc
     */
    public function setState(string $state)
    {
        $this->state = $state;
    }

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
    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    /**
     * @inheritDoc
     */
    public function getPostalCode(): string
    {
        return $this->postal_code;
    }

    /**
     * @inheritDoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Validate required fields
     *
     * @param mixed  $value
     * @param string $name
     *
     * @throws \Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException
     */
    protected function validateRequired($value, $name)
    {
        if (strlen(trim($value)) === 0) {
            throw new PaypalExpressException("Parameter `{$name}` is required");
        }
    }
}
