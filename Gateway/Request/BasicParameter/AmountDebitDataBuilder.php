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
     * @var float|null
     */
    private $amount;

    /**
     * @var CurrencyDataBuilder
     */
    private $currencyDataBuilder;

    /**
     * Constructor
     *
     * @param CurrencyDataBuilder $currencyDataBuilder
     */
    public function __construct(
        CurrencyDataBuilder $currencyDataBuilder
    ) {
        $this->currencyDataBuilder = $currencyDataBuilder;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $this->currencyDataBuilder->getAllowedCurrencies($paymentDO->getPayment()->getMethodInstance());

        $this->amount = null;

        if ($this->getAmount($order)) {
            return [
                self::AMOUNT_DEBIT => $this->getAmount($order)
            ];
        } else {
            throw new BuckarooException(__('Total of the order can not be empty.'));
        }
    }

    /**
     * Get Amount
     *
     * @param Order|null $order
     *
     * @return float|null
     */
    public function getAmount(?Order $order = null): ?float
    {
        if (empty($this->amount)) {
            $this->setAmount($order);
        }

        return $this->amount;
    }

    /**
     * Set Amount
     *
     * @param Order $order
     *
     * @return $this
     */
    public function setAmount(Order $order): AmountDebitDataBuilder
    {
        if ($this->currencyDataBuilder->getCurrency($order) == $order->getOrderCurrencyCode()) {
            $this->amount = $order->getGrandTotal();
        } else {
            $this->amount = $order->getBaseGrandTotal();
        }

        return $this;
    }
}
