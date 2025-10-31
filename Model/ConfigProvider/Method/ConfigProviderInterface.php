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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

interface ConfigProviderInterface
{
    /**
     * Get Active Config Valuue
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getActive($store = null);

    /**
     * Get Available In Backend
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getAvailableInBackend($store = null);

    /**
     * Get Send order confirmation email
     *
     * @param  null|int|string $store
     * @return bool
     */
    public function hasOrderEmail($store = null): bool;

    /**
     * Get Payment fee Float Value
     *
     * @param  null|int|string $store
     * @return false|float
     */
    public function getPaymentFee($store = null);

    /**
     * Get Payment fee frontend label
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getPaymentFeeLabel($store = null);

    /**
     * Get Method specific status enabled
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getActiveStatus($store = null);

    /**
     * Get Method specific success status
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusSuccess($store = null);

    /**
     * Get Method specific failed status
     *
     * @param  null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusFailed($store = null);

    /**
     * Get Allowed Currencies for specific payment method or get defaults
     *
     * @return array
     */
    public function getAllowedCurrencies();

    /**
     * Get Base Allowed Currencies
     *
     * @return array
     */
    public function getBaseAllowedCurrencies();

    /**
     * Returns an array of base allowed countries.
     *
     * @return array
     */
    public function getBaseAllowedCountries();

    /**
     * Get buckaroo payment fee
     *
     * @return string
     */
    public function getBuckarooPaymentFeeLabel();

    /**
     * Generate the url to the desired asset.
     *
     * @param  string $imgName
     * @return string
     */
    public function getImageUrl($imgName);
}
