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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnCustomerGroupValidator extends AbstractValidator
{
    /**
     * @var Customer
     */
    public $customerHelper;

    /**
     * @param Customer               $customerHelper
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        Customer $customerHelper,
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->customerHelper = $customerHelper;
    }

    /**
     * Available Based on Costumer Group
     *
     * @param array $validationSubject
     *
     * @throws Exception
     * @throws LocalizedException
     *
     * @return ResultInterface
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

        $checkCustomerGroup = $this->customerHelper->checkCustomerGroup($paymentMethodCode);
        if ($paymentMethodCode === 'buckaroo_magento2_billink' && !$checkCustomerGroup) {
            $checkCustomerGroup = $this->customerHelper->checkCustomerGroup($paymentMethodCode, true);
        }

        if (!$checkCustomerGroup) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
