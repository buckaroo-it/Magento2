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

namespace Buckaroo\Magento2\Api\Data\ExpressMethods;

interface ShippingAddressRequestInterface
{
    /**
     * Set city
     *
     * @param string $city
     */
    public function setCity(string $city);

    /**
     * Set country code
     *
     * @param string $country_code
     */
    public function setCountryCode(string $country_code);

    /**
     * Set postal code
     *
     * @param string $postal_code
     */
    public function setPostalCode(string $postal_code);

    /**
     * Set state
     *
     * @param string $state
     */
    public function setState(string $state);

    /**
     * Get city
     *
     * @return string
     */
    public function getCity(): string;

    /**
     * Get country code
     *
     * @return string
     */
    public function getCountryCode(): string;

    /**
     * Get postal code
     *
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * Get state
     *
     * @return string|null
     */
    public function getState(): ?string;
}
