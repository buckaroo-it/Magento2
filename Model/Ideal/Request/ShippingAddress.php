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

namespace Buckaroo\Magento2\Model\Ideal\Request;

use Buckaroo\Magento2\Api\Data\Ideal\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Model\Ideal\IdealException;

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
    protected $telephone;

    /**
     * @var string
     */
    protected $firstname;

    /**
     * @var string
     */
    protected $lastname;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $street;

    /**
     * Set the city value.
     *
     * @param string $city
     *
     * @return void
     */
    public function setCity(string $city)
    {
        $this->validateRequired($city, 'city');
        $this->city = $city;
    }

    /**
     * Set the country code value.
     *
     * @param string $country_code
     *
     * @return void
     */
    public function setCountryCode(string $country_code)
    {
        $this->validateRequired($country_code, 'country_code');
        $this->country_code = $country_code;
    }

    /**
     * Set the postal code value.
     *
     * @param string $postal_code
     *
     * @return void
     */
    public function setPostalCode(string $postal_code)
    {
        $this->validateRequired($postal_code, 'postal_code');
        $this->postal_code = $postal_code;
    }

    /**
     * Set the telephone value.
     *
     * @param string $telephone
     *
     * @return void
     */
    public function setTelephone(string $telephone)
    {
        $this->validateRequired($telephone, 'telephone');
        $this->telephone = $telephone;
    }

    /**
     * Set the first name value.
     *
     * @param string $firstname
     *
     * @return void
     */
    public function setFirstname(string $firstname)
    {
        $this->validateRequired($firstname, 'firstname');
        $this->firstname = $firstname;
    }

    /**
     * Set the last name value.
     *
     * @param string $lastname
     *
     * @return void
     */
    public function setLastname(string $lastname)
    {
        $this->validateRequired($lastname, 'lastname');
        $this->lastname = $lastname;
    }

    /**
     * Set the email value.
     *
     * @param string $email
     *
     * @return void
     */
    public function setEmail(string $email)
    {
        $this->validateRequired($email, 'email');
        $this->email = $email;
    }

    /**
     * Set the street value.
     *
     * @param string $street
     *
     * @return void
     */
    public function setStreet(string $street)
    {
        $this->validateRequired($street, 'street');
        $this->street = $street;
    }

    /**
     * Get the city value.
     *
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * Get the country code value.
     *
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    /**
     * Get the postal code value.
     *
     * @return string
     */
    public function getPostalCode(): string
    {
        return $this->postal_code;
    }

    /**
     * Get the telephone value.
     *
     * @return string
     */
    public function getTelephone(): string
    {
        return $this->telephone;
    }

    /**
     * Get the first name value.
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * Get the last name value.
     *
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * Get the email value.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the street value.
     *
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * Validate required fields
     *
     * @param mixed  $value
     * @param string $name
     *
     * @throws \Buckaroo\Magento2\Model\Ideal\IdealException
     */
    protected function validateRequired($value, $name)
    {
        if (strlen(trim($value)) === 0) {
            throw new IdealException("Parameter `{$name}` is required");
        }
    }
}
