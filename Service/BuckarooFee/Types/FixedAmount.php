<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Service\BuckarooFee\Types;

use Buckaroo\Magento2\Service\Tax\TaxCalculate;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Buckaroo\Magento2\Service\BuckarooFee\Result;
use Buckaroo\Magento2\Service\BuckarooFee\ResultFactory;

class FixedAmount
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var TaxCalculate
     */
    private $taxCalculate;

    /**
     * @param ResultFactory $resultFactory
     * @param TaxCalculate $taxCalculate
     */
    public function __construct(ResultFactory $resultFactory, TaxCalculate $taxCalculate)
    {
        $this->resultFactory = $resultFactory;
        $this->taxCalculate = $taxCalculate;
    }

    public function calculate(CartInterface $cart, Total $total, float $amount){

        $tax = $this->taxCalculate->getTaxFromAmountIncludingTax($cart, $amount);
        /** @var Result $result */
        $result = $this->resultFactory->create();
        $result->setAmount($amount - $tax);
        $result->setTaxAmount($tax);

        return $result;
    }
}
