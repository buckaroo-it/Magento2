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

namespace Buckaroo\Magento2\Gateway\Request\Capayable\Builder;

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
        return (-1 * round($discount, 2));
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
