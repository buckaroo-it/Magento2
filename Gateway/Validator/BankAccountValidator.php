<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Sales\Model\Order\Payment;
use Zend\Validator\Iban;

/**
 * Class IssuerValidator
 * @package Magento\Payment\Gateway\Validator
 * @api
 * @since 100.0.2
 */
class BankAccountValidator extends AbstractValidator
{
    const BIC_NUMBER_REGEX = '^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$^';

    private \Zend\Validator\Iban $ibanValidator;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Iban $ibanValidator
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        \Zend\Validator\Iban $ibanValidator
    ) {
        $this->ibanValidator = $ibanValidator;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return bool|ResultInterface
     * @throws NotFoundException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentInfo = $validationSubject['payment'];

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this->createResult(true);
        }

        $customerBic = $paymentInfo->getAdditionalInformation('customer_bic');
        $customerIban = $paymentInfo->getAdditionalInformation('customer_iban');
        $customerAccountName = $paymentInfo->getAdditionalInformation('customer_account_name');

        $fails = [];
        if (empty($customerAccountName) || str_word_count($customerAccountName) < 2) {
            $fails[] = __('Please enter a valid bank account holder name');
        }

        if ($paymentInfo instanceof Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }

        if (empty($customerIban) || !$this->ibanValidator->isValid($customerIban)) {
            $fails[] = __('Please enter a valid bank account number');
        }

        if ($billingCountry != 'NL' && !preg_match(self::BIC_NUMBER_REGEX, $customerBic)) {
            $fails[] = __('Please enter a valid BIC number');
        }

        $isValid = false;
        if (empty($fails)) {
            $isValid = true;
        }

        return $this->createResult($isValid, $fails);
    }
}
