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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

class RefundFieldsFactory
{
    /**
     * @var array
     */
    protected $refundFields;

    /**
     * @param array $refundFields
     */
    public function __construct(
        array $refundFields = []
    ) {
        $this->refundFields = $refundFields;
    }

    /**
     * Retrieve proper transaction builder for the specified transaction type.
     *
     * @param string $paymentMethod
     *
     * @throws \LogicException
     *
     * @return array|false
     */
    public function get(string $paymentMethod)
    {
        if (!isset($this->refundFields)) {
            throw new \LogicException('No refund fields are set.');
        }

        if (empty($this->refundFields[$paymentMethod])) {
            return false;
        }

        return $this->refundFields[$paymentMethod];
    }
}
