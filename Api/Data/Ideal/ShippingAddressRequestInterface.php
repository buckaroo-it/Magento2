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
     * @param string|null $telephone
     */
    public function setTelephone(string $telephone);

    /**
     * Set telephone
     *
     * @param string|null $firstname
     */
    public function setFirstname(string $firstname);

    /**
     * Set telephone
     *
     * @param string|null $lastname
     */
    public function setLastname(string $lastname);

    /**
     * Set telephone
     *
     * @param string|null $email
     */
    public function setEmail(string $email);

    /**
     * Set telephone
     *
     * @param string|null $street
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
     * @return string|null
     */
    public function getTelephone(): string;

    /**
     * Get telephone
     *
     * @return string|null
     */
    public function getFirstname(): string;

    /**
     * Get telephone
     *
     * @return string|null
     */
    public function getLastname(): string;

    /**
     * Get telephone
     *
     * @return string|null
     */
    public function getEmail(): string;

    /**
     * Get telephone
     *
     * @return string|null
     */
    public function getStreet(): string;
}
