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

namespace Buckaroo\Magento2\Api\Data;

interface InvoiceInterface
{
    /**
     * Set Invoice Transaction Id
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInvoiceTransactionId($value);

    /**
     * Get Invoice Transaction Id
     *
     * @return string
     */
    public function getInvoiceTransactionId();

    /**
     * Set Invoice Number
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInvoiceNumber($value);

    /**
     * Get Invoice Number
     *
     * @return string
     */
    public function getInvoiceNumber();
}
