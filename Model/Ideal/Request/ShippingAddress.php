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
    protected $city;

    protected $country_code;

    protected $postal_code;

    protected $telephone;

    protected $firstname;

    protected $lastname;

    protected $email;

    protected $street;

    /** @inheritDoc */
    public function setCity(string $city)
    {
        $this->validateRequired($city, 'city');
        $this->city = $city;
    }

    /** @inheritDoc */
    public function setCountryCode(string $country_code)
    {
        $this->validateRequired($country_code, 'country_code');
        $this->country_code = $country_code;
    }

    /** @inheritDoc */
    public function setPostalCode(string $postal_code)
    {
        $this->validateRequired($postal_code, 'postal_code');
        $this->postal_code = $postal_code;
    }

    /** @inheritDoc */
    public function setTelephone(string $telephone)
    {
        $this->validateRequired($telephone, 'telephone');
        $this->telephone = $telephone;
    }

    /** @inheritDoc */
    public function setFirstname(string $firstname)
    {
        $this->validateRequired($firstname, 'firstname');
        $this->firstname = $firstname;
    }

    /** @inheritDoc */
    public function setLastname(string $lastname)
    {
        $this->validateRequired($lastname, 'lastname');
        $this->lastname = $lastname;
    }

    /** @inheritDoc */
    public function setEmail(string $email)
    {
        $this->validateRequired($email, 'email');
        $this->email = $email;
    }

    /** @inheritDoc */
    public function setStreet(string $street)
    {
        $this->validateRequired($street, 'street');
        $this->street = $street;
    }

    /** @inheritDoc */
    public function getCity(): string
    {
        return $this->city;
    }

    /** @inheritDoc */
    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    /** @inheritDoc */
    public function getPostalCode(): string
    {
        return $this->postal_code;
    }

    /** @inheritDoc */
    public function getTelephone(): string
    {
        return $this->telephone;
    }

    /** @inheritDoc */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /** @inheritDoc */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /** @inheritDoc */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** @inheritDoc */
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
