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
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data\ExpressMethods;

interface ShippingAddressRequestInterface
{
    /**
     * Set city
     *
     * @param string $city
     *
     * @return void
     */
    public function setCity(string $city);

    /**
     * Set country code
     *
     * @param string $countryCode
     *
     * @return void
     */
    public function setCountryCode(string $countryCode);

    /**
     * Set postal code
     *
     * @param string $postalCode
     *
     * @return void
     */
    public function setPostalCode(string $postalCode);

    /**
     * Set state
     *
     * @param string $state
     *
     * @return void
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
