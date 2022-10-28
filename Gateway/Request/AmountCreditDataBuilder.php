<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class AmountCreditDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    const AMOUNT_CREDIT = 'amountCredit';

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

    protected $logger2;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    protected $payRemainder = 0;

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param Factory $configProviderMethodFactory
     * @param BuckarooLog $buckarooLog
     * @param \Magento\Framework\App\RequestInterface $request
     * @param null|int|float|double $amount
     * @param null|string $currency
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        Factory     $configProviderMethodFactory,
        BuckarooLog $buckarooLog,
        \Magento\Framework\App\RequestInterface $request,
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

        $this->objectManager               = $objectManager;
        $this->logger2                     = $buckarooLog;
        $this->request                     = $request;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $this->setOrder($payment->getOrder()->getOrder());

        $amount =  $buildSubject['amount'] ?? $this->getOrder()->getBaseGrandTotal();

        $amount = $this->refundGroupTransactions($payment->getPayment(), $amount);

        $this->setAmount($amount);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit Amount less than or equal to 0');
        }

        if (!$this->getCurrency()) {
            $this->setRefundCurrencyAndAmount();
        }

        return [
            self::AMOUNT_CREDIT => $this->getAmount()
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
     * @throws Exception
     */
    protected function setRefundCurrencyAndAmount()
    {
        /**
         * @var \Buckaroo\Magento2\Model\Method\AbstractMethod $methodInstance
         */
        $methodInstance = $this->order->getPayment()->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode;

        $configProvider = $this->configProviderMethodFactory->get($method);
        $allowedCurrencies = $configProvider->getAllowedCurrencies($this->order->getStore());

        if (in_array($this->order->getOrderCurrencyCode(), $allowedCurrencies)) {
            /**
             * @todo find a way to fix the cumulative rounding issue that occurs in creditmemos.
             *       This problem occurs when the creditmemo is being refunded in the order's currency, rather than the
             *       store's base currency.
             */
            $this->setCurrency($this->order->getOrderCurrencyCode());
            $this->setAmount(round($this->getAmount() * $this->order->getBaseToOrderRate(), 2));
        } elseif (in_array($this->order->getBaseCurrencyCode(), $allowedCurrencies)) {
            $this->setCurrency($this->order->getBaseCurrencyCode());
        } else {
            throw new Exception(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }
    }

    public function refundGroupTransactions(InfoInterface $payment, $amount)
    {
        $this->logger2->addDebug(__METHOD__ . '|1|');

        $order                   = $payment->getOrder();
        $totalOrder              = $order->getBaseGrandTotal();
        $paymentGroupTransaction = $this->objectManager->create('\Buckaroo\Magento2\Helper\PaymentGroupTransaction');

        $requestParams = $this->request->getParams();

        $this->logger2->addDebug(__METHOD__ . '|20|' . var_export([$amount, $totalOrder, $amount >= 0.01], true));

        if ($amount >= 0.01) {
            $groupTransactionAmount = $paymentGroupTransaction->getGroupTransactionAmount($order->getIncrementId());
            if (
                ($groupTransactionAmount > 0.01)
                &&
                empty($requestParams['creditmemo']['buckaroo_already_paid'])
                &&
                !empty($requestParams['creditmemo']['adjustment_negative'])
            ) {
                $this->logger2->addDebug(__METHOD__ . '|22|');
                $payment->getOrder()->setAdjustmentNegative(0);
            }
            if ($amount == $order->getBaseGrandTotal() && $groupTransactionAmount > 0) {
                $this->logger2->addDebug(__METHOD__ . '|25|' . var_export($groupTransactionAmount, true));
                $this->payRemainder = $amount - $groupTransactionAmount;
                return $amount - $groupTransactionAmount;
            }

            if ($amount > $totalOrder) {
                return $totalOrder;
            }
            return $amount;
        }
        return 0;
    }
}
