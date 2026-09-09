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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

interface TransactionResponseInterface
{
    /**
     * Get transaction id
     *
     * @return string
     */
    public function getTransactionId(): string;

    /**
     * Get giftcard name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Get amount
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Get giftcard code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set data
     *
     * Magento's webapi TypeProcessor reflects over every public method of a data interface and
     * refuses to serialise the response unless each one declares a return type, so this annotation
     * is load bearing rather than decorative - without it any REST call returning this interface
     * fails with "Method's return type must be specified using @return annotation".
     *
     * @param array $data
     *
     * @return $this
     */
    public function addData(array $data);
}
