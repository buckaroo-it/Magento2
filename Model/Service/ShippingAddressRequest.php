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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;

class ShippingAddressRequest implements ShippingAddressRequestInterface
{
    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $countryCode;

    /**
     * @var string
     */
    protected $postalCode;

    /**
     * @var string
     */
    protected $state;

    /**
     * @inheritdoc
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @inheritdoc
     *
     * @throws ExpressMethodsException
     */
    public function setCity(string $city)
    {
        $this->validateRequired($city, 'city');
        $this->city = $city;
    }

    /**
     * Validate required fields
     *
     * @param mixed  $value
     * @param string $name
     *
     * @throws ExpressMethodsException
     */
    protected function validateRequired($value, string $name)
    {
        if (strlen(trim($value)) === 0) {
            throw new ExpressMethodsException("Parameter `{$name}` is required");
        }
    }

    /**
     * @inheritdoc
     */
    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * @inheritdoc
     */
    public function setCountryCode(string $countryCode)
    {
        $this->validateRequired($countryCode, 'countryCode');
        $this->countryCode = $countryCode;
    }

    /**
     * @inheritdoc
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * @inheritdoc
     */
    public function setPostalCode(string $postalCode)
    {
        $this->validateRequired($postalCode, 'postalCode');
        $this->postalCode = $postalCode;
    }

    /**
     * @inheritdoc
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @inheritdoc
     */
    public function setState(string $state)
    {
        $this->state = $state;
    }
}
