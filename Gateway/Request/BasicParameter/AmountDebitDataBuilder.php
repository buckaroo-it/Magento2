<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AmountDebitDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    public const AMOUNT_DEBIT = 'amountDebit';

    /**
     * @inheritdoc
     *
     * @throws BuckarooException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $amount = $this->getAmount($order);

        if (!$amount) {
            throw new BuckarooException(__('Total of the order can not be empty.'));
        }

        return [
            self::AMOUNT_DEBIT => $amount
        ];
    }

    /**
     * Get the order total in the order currency.
     *
     * Transactions are always sent in the order currency (the CurrencyDataBuilder
     * rejects the request when the payment method does not support it, and the
     * AvailableBasedOnCurrencyValidator hides the method up front), so the amount
     * is always the order-currency grand total.
     *
     * @param Order $order
     *
     * @return float|null
     */
    public function getAmount(Order $order): ?float
    {
        $total = $order->getGrandTotal();

        return $total === null ? null : (float)$total;
    }
}
