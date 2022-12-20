<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AmountDebitDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    const AMOUNT_DEBIT = 'amountDebit';

    /**
     * @var int
     */
    private $amount;

    /**
     * @var CurrencyDataBuilder
     */
    private CurrencyDataBuilder $currencyDataBuilder;

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
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        return [
            self::AMOUNT_DEBIT => $this->getAmount($order)
        ];
    }

    /**
     * Get Amount
     *
     * @param Order|null $order
     * @return string
     * @throws Exception
     */
    public function getAmount($order = null)
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
     * @return $this
     * @throws Exception
     */
    public function setAmount($order)
    {
        if ($this->currencyDataBuilder->getCurrency($order) == $order->getOrderCurrencyCode()) {
            $this->amount = $order->getGrandTotal();
        }
        $this->amount = $order->getBaseGrandTotal();

        return $this;
    }
}
