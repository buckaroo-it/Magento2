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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Billink;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ZakelijkOpRekening;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

class AvailableBasedOnAmountValidator extends AbstractValidator
{
    /**
     * Check if the grand total exceeds the maximum allowed total.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $quote = SubjectReader::readQuote($validationSubject);

        [$minimum, $maximum] = $this->getAmountLimits($paymentMethodInstance, $quote);

        $total = $quote->getGrandTotal();

        $isValid = !($total < 0.01
            || $maximum !== null && $total > $maximum
            || $minimum !== null && $total < $minimum);

        return $this->createResult($isValid);
    }

    /**
     * Get the minimum and maximum order amount for the payment method.
     *
     * @param MethodInterface $paymentMethodInstance
     * @param Quote           $quote
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function getAmountLimits(MethodInterface $paymentMethodInstance, Quote $quote): array
    {
        $storeId = $quote->getStoreId();
        $suffix = $this->useB2bLimits($paymentMethodInstance, $quote) ? '_b2b' : '';

        $minimum = $paymentMethodInstance->getConfigData('min_amount' . $suffix, $storeId);
        $maximum = $paymentMethodInstance->getConfigData('max_amount' . $suffix, $storeId);

        if ($paymentMethodInstance->getCode() === ZakelijkOpRekening::CODE) {
            $minimum = max((float)$minimum, ZakelijkOpRekening::MIN_ORDER_AMOUNT);
            $maximum = ($maximum === null || $maximum === '')
                ? ZakelijkOpRekening::MAX_ORDER_AMOUNT
                : min((float)$maximum, ZakelijkOpRekening::MAX_ORDER_AMOUNT);
        }

        return [$minimum, $maximum];
    }

    /**
     * Check if the B2B amount limits apply to this quote and payment method.
     *
     * @param MethodInterface $paymentMethodInstance
     * @param Quote           $quote
     *
     * @return bool
     */
    private function useB2bLimits(MethodInterface $paymentMethodInstance, Quote $quote): bool
    {
        if (!in_array($paymentMethodInstance->getCode(), [Afterpay20::CODE, Billink::CODE], true)) {
            return false;
        }

        return $quote->getShippingAddress()->getCompany() || $quote->getBillingAddress()->getCompany();
    }
}
