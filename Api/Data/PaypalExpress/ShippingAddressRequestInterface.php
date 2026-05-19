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

namespace Buckaroo\Magento2\Api\Data\PaypalExpress;

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
     * @param string|null $state
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
    public function getState(): string;
}
