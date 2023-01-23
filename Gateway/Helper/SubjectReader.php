<?php

namespace Buckaroo\Magento2\Gateway\Helper;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

class SubjectReader
{
    /**
     * Reads payment method instance from subject
     *
     * @param array $subject
     * @return MethodInterface
     */
    public static function readPaymentMethodInstance(array $subject)
    {
        if (!isset($subject['paymentMethodInstance'])
            || !$subject['paymentMethodInstance'] instanceof MethodInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        return $subject['paymentMethodInstance'];
    }

    /**
     * Reads quote from subject
     *
     * @param array $subject
     * @return Quote
     */
    public static function readQuote(array $subject)
    {
        if (!isset($subject['quote'])
            || !$subject['quote'] instanceof Quote
        ) {
            throw new \InvalidArgumentException('Quote data object should be provided.');
        }

        return $subject['quote'];
    }

    /**
     * Reads payment from subject
     *
     * @param array $subject
     * @return PaymentDataObjectInterface
     */
    public static function readPayment(array $subject)
    {
        return \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($subject);
    }

    /**
     * Reads amount from subject
     *
     * @param array $subject
     * @return mixed
     */
    public static function readAmount(array $subject)
    {
        \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($subject);
    }
}