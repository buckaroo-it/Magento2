<?php

namespace TIG\Buckaroo\Model;

/**
 * Class RefundFieldsFactory
 *
 * @package TIG\Bukcaroo\Model
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
     * @throws \LogicException|\TIG\Buckaroo\Exception
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
