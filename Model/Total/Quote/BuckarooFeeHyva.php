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

namespace Buckaroo\Magento2\Model\Total\Quote;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Service\BuckarooFee\Calculate;
use Buckaroo\Magento2\Service\HyvaCheckoutConfig;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class BuckarooFeeHyva extends AbstractTotal
{
    /**
     * @var PriceCurrencyInterface
     */
    public $priceCurrency;

    /**
     * @var PaymentGroupTransaction
     */
    public $groupTransaction;

    /**
     * @var Calculate
     */
    protected $calculate;

    /**
     * @var HyvaCheckoutConfig
     */
    protected $configProvider;

    /**
     * @param PriceCurrencyInterface  $priceCurrency
     * @param PaymentGroupTransaction $groupTransaction
     * @param HyvaCheckoutConfig      $configProvider
     * @param Calculate               $calculate
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        HyvaCheckoutConfig $configProvider,
        Calculate $calculate
    ) {
        $this->setCode('buckaroo_fee_hyva');

        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
        $this->configProvider = $configProvider;
        $this->calculate = $calculate;
    }

    /**
     * Collect grand total address amount
     *
     * @param Quote                       $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total                       $total
     *
     * @throws Exception
     * @return $this
     */
    public function collect(
        Quote                                               $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        if (!$this->configProvider->isHyvaCheckoutEnabled()) {
            return $this;
        }

        parent::collect($quote, $shippingAssignment, $total);

        // Ensure that shipping assignment has items, otherwise skip processing.
        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee(0);

        $orderId = $quote->getReservedOrderId();

        // Check if already paid amount is affecting the calculation
        if ($this->groupTransaction->getAlreadyPaid($orderId) > 0) {
            return $this;
        }

        $result = $this->calculate->calculatePaymentFee($quote, $total);

        if ($result === null || $result->getAmount() < 0.01) {
            return $this;
        }

        $amount = $this->priceCurrency->convert($result->getRoundedAmount());

        // Add fee amounts using addTotalAmount to ensure proper accumulation with other totals
        $total->addTotalAmount('buckaroo_fee_hyva', $amount);
        $total->addBaseTotalAmount('buckaroo_fee_hyva', $result->getRoundedAmount());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooFee($amount);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooFee($result->getRoundedAmount());

        return $this;
    }

    /**
     * Add buckaroo fee information to address
     *
     * @param  Quote $quote
     * @param  Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        return [
            'code'  => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => $total->getBuckarooFee(),
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return Phrase
     */
    public function getLabel()
    {
        return __('Payment Fee');
    }
}
