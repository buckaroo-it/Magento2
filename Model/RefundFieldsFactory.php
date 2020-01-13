<?php

namespace Buckaroo\Magento2\Model;

/**
 * Class RefundFieldsFactory
 *
 * @package Buckaroo\Magento2\Model
 */
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
     * @return array|false
     *
     * @throws \LogicException|\Buckaroo\Magento2\Exception
     */
    public function get($paymentMethod)
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
