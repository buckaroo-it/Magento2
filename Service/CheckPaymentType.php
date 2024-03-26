<?php

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CheckPaymentType
{
    /**
     * Is one of Buckaroo payment methods by string
     *
     * @param string $paymentMethod
     * @return boolean
     */
    public function isBuckarooMethod(string $paymentMethod): bool
    {
        return strpos($paymentMethod, 'buckaroo_magento2_') !== false;
    }

    /**
     * Is one of Buckaroo payment methods by PaymentMethod
     *
     * @param OrderPaymentInterface|null $payment
     * @return boolean
     */
    public function isBuckarooPayment(?OrderPaymentInterface $payment): bool
    {
        if (!$payment instanceof OrderPaymentInterface) {
            return false;
        }
        return strpos($payment->getMethod(), 'buckaroo_magento2') !== false;
    }

    /**
     * Check if user is on the payment provider page
     *
     * @param OrderPaymentInterface|null $payment
     * @return boolean
     */
    public function isPaymentInTransit(?OrderPaymentInterface $payment): bool
    {
        if (!$payment instanceof OrderPaymentInterface) {
            return false;
        }

        return $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_PAYMENT_IN_TRANSIT) == true;
    }
}
