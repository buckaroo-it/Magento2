<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Pospayment;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnPOSValidator extends AbstractValidator
{
    /**
     * @var Pospayment
     */
    public Pospayment $pospaymentConfiguration;

    /**
     * @var BuckarooHelper
     */
    public BuckarooHelper $helper;

    /**
     * @var PaymentHelper
     */
    private PaymentHelper $paymentHelper;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Pospayment $pospaymentConfiguration
     * @param BuckarooHelper $helper
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Pospayment             $pospaymentConfiguration,
        BuckarooHelper         $helper,
        PaymentHelper          $paymentHelper
    ) {
        parent::__construct($resultFactory);
        $this->pospaymentConfiguration = $pospaymentConfiguration;
        $this->helper = $helper;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Available Based on Costumer Group
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject): ResultInterface
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

        $quote = SubjectReader::readQuote($validationSubject);

        if ($paymentMethodCode !== Pospayment::CODE && $this->pospaymentConfiguration->getActive()) {
            $posPaymentMethodInstance = $this->paymentHelper->getMethodInstance(Pospayment::CODE);
            if ($posPaymentMethodInstance->isAvailable($quote)) {
                $isValid = false;
                if ($this->checkPosOtherPaymentMethods($paymentMethodCode)) {
                    $isValid = true;
                }
            }
        }

        return $this->createResult($isValid);
    }

    /**
     * Check if payment method should be display with POS
     *
     * @param string $paymentMethodCode
     * @return bool
     */
    private function checkPosOtherPaymentMethods($paymentMethodCode)
    {
        $otherPaymentMethods = $this->pospaymentConfiguration->getOtherPaymentMethods();
        if (in_array(
            $this->helper->getBuckarooMethod($paymentMethodCode),
            explode(',', $otherPaymentMethods)
        )) {
            return true;
        }

        return false;
    }
}
