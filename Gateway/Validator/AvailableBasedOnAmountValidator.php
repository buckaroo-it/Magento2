<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Model\MethodInterface;

class AvailableBasedOnAmountValidator extends AbstractValidator
{
    /**
     * Available Based on Amount
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;

        if (!isset($validationSubject['paymentMethodInstance']) || !isset($validationSubject['quote'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        /** @var MethodInterface $paymentMethodInstance */
        $paymentMethodInstance = $validationSubject['paymentMethodInstance'];

        $storeId = $validationSubject['quote']->getStoreId();
        $maximum = $paymentMethodInstance->getConfigData('max_amount', $storeId);
        $minimum = $paymentMethodInstance->getConfigData('min_amount', $storeId);

        $total = $validationSubject['quote']->getGrandTotal();

        if ($total < 0.01
            || $maximum !== null && $total > $maximum
            || $minimum !== null && $total < $minimum) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
