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

namespace Buckaroo\Magento2\Api;

interface TransactionResponseInterface
{
    /**
     * Get status code
     *
     * @return string|null
     */
    public function getStatusCode();

    /**
     * Get service code
     *
     * @return string|null
     */
    public function getServiceCode();

    /**
     * Check if response is of status code
     *
     * @param mixed $statusCode
     *
     * @return boolean
     */
    public function isStatusCode($statusCode);
}
