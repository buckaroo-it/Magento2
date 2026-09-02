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

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class SubTotalsDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $subTotals = [];

        $discount = $this->getDiscount();
        if ($discount < 0) {
            $subTotals[] = [
                'name' => 'Discount',
                'value' => $discount
            ];
        }

        $fee = $this->getFee();
        if ($fee > 0) {
            $subTotals[] = [
                'name' => 'Payment Fee',
                'value' => $fee
            ];
        }

        $shipping = $this->getShipping();
        if ($shipping > 0) {
            $subTotals[] = [
                'name' => 'Shipping Costs',
                'value' => $shipping
            ];
        }

        return [
            'subtotals' => $subTotals
        ];
    }

    /**
     * Get discount
     *
     * @return float
     */
    protected function getDiscount()
    {
        $discount = abs((float)$this->getOrder()->getDiscountAmount());
        return -1 * round($discount, 2);
    }

    /**
     * Get buckaroo fee
     *
     * @return float
     */
    protected function getFee(): float
    {
        return round(
            (float)$this->getOrder()->getBuckarooFee()
            + (float)$this->getOrder()->getBuckarooFeeTaxAmount(),
            2
        );
    }

    /**
     * Get shipping amount
     *
     * @return float
     */
    protected function getShipping(): float
    {
        return round((float)$this->getOrder()->getShippingInclTax(), 2);
    }
}
