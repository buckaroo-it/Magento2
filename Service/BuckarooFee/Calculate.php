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
     * @param Factory $configProviderMethodFactory
     * @param \Buckaroo\Magento2\Service\BuckarooFee\Types\FixedAmount $fixedAmount
     * @param Percentage $percentage
     */
    public function __construct(Factory $configProviderMethodFactory, FixedAmount $fixedAmount, Percentage $percentage)
    {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->fixedAmount = $fixedAmount;
        $this->percentage = $percentage;
    }

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

    public function getPaymentFee(Quote $quote)
    {
        $paymentMethod = $quote->getPayment()->getMethod();

        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return null;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        $buckarooPaymentMethodCode = $methodInstance->buckarooPaymentMethodCode;
        if (!$this->configProviderMethodFactory->has($buckarooPaymentMethodCode)) {
            return null;
        }

        $configProvider = $this->configProviderMethodFactory->get($buckarooPaymentMethodCode);
        return trim($configProvider->getPaymentFee($quote->getStore()));
    }
}
