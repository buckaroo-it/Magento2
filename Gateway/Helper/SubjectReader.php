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

namespace Buckaroo\Magento2\Gateway\Helper;

use Buckaroo\Transaction\Response\TransactionResponse;
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
     * @return mixed
     */
    public static function readAmount(array $subject)
    {
        return \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($subject);
    }
}
