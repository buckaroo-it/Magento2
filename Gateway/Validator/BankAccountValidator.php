<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Validator;

use Exception;
use Laminas\Validator\Iban;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Sales\Model\Order\Payment;

/**
 * Class IssuerValidator
 *
 * @api
 *
 * @since 100.0.2
 */
class BankAccountValidator extends AbstractValidator
{
    public const BIC_NUMBER_REGEX = '^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$^';

    /**
     * @var Iban
     */
    private $ibanValidator;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Iban                   $ibanValidator
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Iban $ibanValidator
    ) {
        $this->ibanValidator = $ibanValidator;
        parent::__construct($resultFactory);
    }

    /**
     * Validates the payment information for Buckaroo gateway.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentInfo = $validationSubject['payment'];

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
