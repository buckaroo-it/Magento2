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
namespace Buckaroo\Magento2\Api\Data\Ideal;

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
     * Set telephone
     *
     * @param string $telephone
     */
    public function setTelephone(string $telephone);

    /**
     * Set telephone
     *
     * @param string $firstname
     */
    public function setFirstname(string $firstname);

    /**
     * Set telephone
     *
     * @param string $lastname
     */
    public function setLastname(string $lastname);

    /**
     * Set telephone
     *
     * @param string $email
     */
    public function setEmail(string $email);

    /**
     * Set telephone
     *
     * @param string $street
     */
    public function setStreet(string $street);

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
     * Get telephone
     *
     * @return string
     */
    public function getTelephone(): string;

    /**
     * Get telephone
     *
     * @return string
     */
    public function getFirstname(): string;

    /**
     * Get telephone
     *
     * @return string
     */
    public function getLastname(): string;

    /**
     * Get telephone
     *
     * @return string
     */
    public function getEmail(): string;

    /**
     * Get telephone
     *
     * @return string
     */
    public function getStreet(): string;
}
