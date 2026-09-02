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

namespace Buckaroo\Magento2\Service\BuckarooFee;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Service\BuckarooFee\Types\FixedAmount;
use Buckaroo\Magento2\Service\BuckarooFee\Types\Percentage;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;

class Calculate
{
    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var \Buckaroo\Magento2\Service\BuckarooFee\Types\FixedAmount
     */
    private $fixedAmount;

    /**
     * @var \Buckaroo\Magento2\Service\BuckarooFee\Types\Percentage
     */
    private $percentage;

    /**
     * @param Factory                                                  $configProviderMethodFactory
     * @param \Buckaroo\Magento2\Service\BuckarooFee\Types\FixedAmount $fixedAmount
     * @param Percentage                                               $percentage
     */
    public function __construct(Factory $configProviderMethodFactory, FixedAmount $fixedAmount, Percentage $percentage)
    {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->fixedAmount = $fixedAmount;
        $this->percentage = $percentage;
    }

    /**
     * Calculate the Buckaroo payment fee for the given quote.
     *
     * @param Quote $quote
     * @param Total $total
     * @return mixed
     */
    public function calculatePaymentFee(Quote $quote, Total $total)
    {
        $paymentFee = $this->getPaymentFee($quote);

        if ($paymentFee === null) {
            return null;
        }
        if (strpos($paymentFee, '%') !== false) {
            return $this->percentage->calculate($quote, $total, $paymentFee);
        }

        return $this->fixedAmount->calculate($quote, (float)$paymentFee);
    }

    /**
     * Get the configured payment fee value for the quote's payment method.
     *
     * @param Quote $quote
     * @return string|null
     */
    public function getPaymentFee(Quote $quote)
    {
        $paymentMethod = $quote->getPayment()->getMethod();

        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return null;
        }

        try {
            $methodInstance = $quote->getPayment()->getMethodInstance();

            if (!$methodInstance || !isset($methodInstance->buckarooPaymentMethodCode)) {
                return null;
            }

            $buckarooPaymentMethodCode = $methodInstance->buckarooPaymentMethodCode;
            if (!$this->configProviderMethodFactory->has($buckarooPaymentMethodCode)) {
                return null;
            }

            $configProvider = $this->configProviderMethodFactory->get($buckarooPaymentMethodCode);
            return trim($configProvider->getPaymentFee($quote->getStore()));
        } catch (\Exception $e) {
            // If payment method instance cannot be loaded, return null (no fee)
            return null;
        }
    }
}
