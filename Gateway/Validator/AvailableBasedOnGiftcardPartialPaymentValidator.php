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
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class AvailableBasedOnGiftcardPartialPaymentValidator extends AbstractValidator
{
    /**
     * Payment methods that do not support partial payments with giftcards
     *
     * @var array
     */
    private const UNSUPPORTED_PAYMENT_METHODS = [
        'billink',
        'klarna',
        'klarnakp',
        'capayableinstallments',
        'transfer',
        'sepadirectdebit',
        'capayablein3',
        'creditcard',
        'mrcash',
        'payperemail',
        'bancontact'
    ];

    /**
     * @var PaymentGroupTransaction
     */
    private $paymentGroupTransaction;

    /**
     * @param PaymentGroupTransaction $paymentGroupTransaction
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * Validate payment availability based on giftcard partial payment
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        if (!isset($validationSubject['paymentMethodInstance']) || !isset($validationSubject['quote'])) {
            return $this->createResult(true);
        }

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $quote = SubjectReader::readQuote($validationSubject);

        $paymentMethodCode = strtolower(str_replace('buckaroo_magento2_', '', $paymentMethodInstance->getCode()));

        if (!in_array($paymentMethodCode, self::UNSUPPORTED_PAYMENT_METHODS, true)) {
            return $this->createResult(true);
        }

        $orderId = $quote->getReservedOrderId();
        if ($orderId) {
            $alreadyPaid = $this->paymentGroupTransaction->getAlreadyPaid($orderId);
            if ($alreadyPaid > 0) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}
