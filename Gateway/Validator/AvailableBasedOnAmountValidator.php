<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;

class AvailableBasedOnAmountValidator extends AbstractValidator
{
    /**
     * Check if the grand total exceeds the maximum allowed total.
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $quote = SubjectReader::readQuote($validationSubject);

        $storeId = $quote->getStoreId();
        $maximum = $paymentMethodInstance->getConfigData('max_amount', $storeId);
        $minimum = $paymentMethodInstance->getConfigData('min_amount', $storeId);

        $total = $quote->getGrandTotal();

        if (
            $total < 0.01
            || $maximum !== null && $total > $maximum
            || $minimum !== null && $total < $minimum
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
