<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class DescriptionDataBuilder implements BuilderInterface
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * Constructor
     *
     * @param Factory $configProviderMethodFactory
     * @param null|int|float|double $amount
     * @param null|string $currency
     */
    public function __construct(
        Account $configProviderAccount
    ) {
        $this->configProviderAccount = $configProviderAccount;
    }

    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $this->setOrder($payment->getOrder()->getOrder());

        $store = $this->getOrder()->getStore();

        return [
            'description' => $this->configProviderAccount->getParsedLabel($store, $this->getOrder())
        ];
    }

    /**
     * @return Order
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
}
