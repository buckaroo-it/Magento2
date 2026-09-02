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

namespace Buckaroo\Magento2\Gateway\Helper;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

// phpcs:disable Magento2.Functions.StaticFunction -- pure stateless helpers, mirrors Magento\Payment\Gateway\Helper\SubjectReader
class SubjectReader
{
    /**
     * Reads payment method instance from the subject
     *
     * @param array $subject
     *
     * @return MethodInterface
     */
    public static function readPaymentMethodInstance(array $subject): MethodInterface
    {
        if (!isset($subject['paymentMethodInstance'])
            || !$subject['paymentMethodInstance'] instanceof MethodInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        return $subject['paymentMethodInstance'];
    }

    /**
     * Reads quote from subject`
     *
     * @param array $subject
     *
     * @return Quote
     */
    public static function readQuote(array $subject): Quote
    {
        if (!isset($subject['quote'])
            || !$subject['quote'] instanceof Quote
        ) {
            throw new \InvalidArgumentException('Quote data object should be provided.');
        }

        return $subject['quote'];
    }

    /**
     * Reads quote from subject
     *
     * @param array $response
     *
     * @return TransactionResponse
     */
    public static function readTransactionResponse(array $response): TransactionResponse
    {
        if (!isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }

        return $response['object'];
    }

    /**
     * Reads payment from subject
     *
     * @param array $subject
     *
     * @return PaymentDataObjectInterface
     */
    public static function readPayment(array $subject): PaymentDataObjectInterface
    {
        return \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($subject);
    }

    /**
     * Reads amount from subject
     *
     * @param array $subject
     *
     * @return mixed
     */
    public static function readAmount(array $subject)
    {
        return \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($subject);
    }
}
