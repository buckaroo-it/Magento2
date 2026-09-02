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
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

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
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $quote = SubjectReader::readQuote($validationSubject);

        $storeId = $quote->getStoreId();

        if (($quote->getShippingAddress()->getCompany() || $quote->getBillingAddress()->getCompany())
            && in_array($paymentMethodInstance->getCode(), [Afterpay20::CODE, Billink::CODE])) {
            $maximum = $paymentMethodInstance->getConfigData('max_amount_b2b', $storeId);
            $minimum = $paymentMethodInstance->getConfigData('min_amount_b2b', $storeId);
        } else {
            $maximum = $paymentMethodInstance->getConfigData('max_amount', $storeId);
            $minimum = $paymentMethodInstance->getConfigData('min_amount', $storeId);
        }

        $total = $quote->getGrandTotal();

        if ($total < 0.01
            || $maximum !== null && $total > $maximum
            || $minimum !== null && $total < $minimum
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
