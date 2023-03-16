<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnCustomerGroupValidator extends AbstractValidator
{
    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @param Data $helper
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        Data                   $helper,
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->helper = $helper;
    }

    /**
     * Available Based on Costumer Group
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

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $paymentMethodCode = $paymentMethodInstance->getCode();

        $checkCustomerGroup = $this->helper->checkCustomerGroup($paymentMethodCode);
        if ($paymentMethodCode === 'buckaroo_magento2_billink' && !$checkCustomerGroup) {
            $checkCustomerGroup = $this->helper->checkCustomerGroup($paymentMethodCode, true);
        }

        if (!$checkCustomerGroup) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
