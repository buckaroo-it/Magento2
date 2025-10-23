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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Exception;

class ShippingAddressRequest implements ShippingAddressRequestInterface
{
    /**
     * @var string
     */
    protected string $city;

    /**
     * @var string
     */
    protected string $countryCode;

    /**
     * @var string
     */
    protected string $postalCode;

    /**
     * @var string
     */
    protected string $state;

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
     * @throws Exception
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
     * @throws Exception
     */
    protected function validateRequired($value, string $name)
    {
        if (strlen(trim($value)) === 0) {
            throw new Exception(__("Parameter `{$name}` is required"));
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
