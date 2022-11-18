<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AmountDebitDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    const AMOUNT_DEBIT = 'amountDebit';
    const CURRENCY = 'currency';

    /** @var Factory */
    protected $configProviderMethodFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var int
     */
    public $amount;

    /**
     * @var string
     */
    public $currency;

    /**
     * Constructor
     *
     * @param Factory $configProviderMethodFactory
     * @param null|int|float|double $amount
     * @param null|string $currency
     */
    public function __construct(
        Factory $configProviderMethodFactory,
                $amount = null,
                $currency = null
    )
    {
        if ($amount !== null) {
            $this->amount = $amount;
        }

        if ($currency !== null) {
            $this->currency = $currency;
        }

        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $this->setOrder($payment->getOrder()->getOrder());

        if (!$this->getCurrency()) {
            $this->setOrderCurrency();
        }

        if ($this->getAmount() < 0.01) {
            $this->setOrderAmount();
        }

        return [
            self::AMOUNT_DEBIT => $this->getAmount(),
            self::CURRENCY => $this->getCurrency()
        ];
    }


    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     *
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return $this
     * @throws \Buckaroo\Magento2\Exception
     */
    private function setOrderCurrency()
    {
        if (in_array($this->order->getOrderCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getOrderCurrencyCode());
        }

        if (in_array($this->order->getBaseCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getBaseCurrencyCode());
        }

        throw new \Buckaroo\Magento2\Exception(
            __("The selected payment method does not support the selected currency or the store's base currency.")
        );
    }

    /**
     * @return $this
     */
    private function setOrderAmount()
    {
        if ($this->getCurrency() == $this->order->getOrderCurrencyCode()) {
            return $this->setAmount($this->order->getGrandTotal());
        }

        return $this->setAmount($this->order->getBaseGrandTotal());
    }

    /**
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    private function getAllowedCurrencies()
    {
        /**
         * @var \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
         */
        $methodInstance = $this->order->getPayment()->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode ?? 'buckaroo_magento2_ideal';

        $configProvider = $this->configProviderMethodFactory->get($method);
        return $configProvider->getAllowedCurrencies();
    }


}
