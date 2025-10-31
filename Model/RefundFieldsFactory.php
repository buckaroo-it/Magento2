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
     * @param  string          $paymentMethod
     * @throws \LogicException
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
