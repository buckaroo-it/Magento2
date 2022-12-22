<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Service\DataBuilderService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class AmountCreditDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    private const AMOUNT_CREDIT = 'amountCredit';
    protected $payRemainder = 0;

    /**
     * @var float
     */
    public $refundAmount;

    /**
     * @var BuckarooLog
     */
    protected $buckarooLog;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var DataBuilderService
     */
    private DataBuilderService $dataBuilderService;

    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $paymentGroupTransaction;

    /**
     * Constructor
     *
     * @param PaymentGroupTransaction $paymentGroupTransaction
     * @param Factory $configProviderMethodFactory
     * @param BuckarooLog $buckarooLog
     * @param RequestInterface $request
     * @param DataBuilderService $dataBuilderService
     */
    public function __construct(
        PaymentGroupTransaction                 $paymentGroupTransaction,
        BuckarooLog                             $buckarooLog,
        \Magento\Framework\App\RequestInterface $request,
        DataBuilderService                      $dataBuilderService
    ) {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
        $this->buckarooLog = $buckarooLog;
        $this->request = $request;
        $this->dataBuilderService = $dataBuilderService;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $baseAmountToRefund = $buildSubject['amount'] ?? $order->getBaseGrandTotal();
        $this->refundAmount = $baseAmountToRefund;

        $this->setRefundGroupTransactions($order, $baseAmountToRefund);

        if ($this->refundAmount <= 0) {
            throw new \InvalidArgumentException('Credit Amount less than or equal to 0');
        }

        $this->setRefundAmount($order);

        return [
            self::AMOUNT_CREDIT => $this->getRefundAmount()
        ];
    }

    /**
     * Get Refund Amount
     *
     * @return float
     */
    public function getRefundAmount()
    {
        return $this->refundAmount;
    }

    /**
     * Set Refund Amount Based on Currency
     *
     * @param Order $order
     */
    protected function setRefundAmount($order)
    {
        /**
         * @todo find a way to fix the cumulative rounding issue that occurs in creditmemos.
         *       This problem occurs when the creditmemo is being refunded in the order's currency, rather than the
         *       store's base currency.
         */
        if ($this->dataBuilderService->getElement('currency') == $order->getOrderCurrencyCode()) {
            $this->refundAmount = round($this->refundAmount * $order->getBaseToOrderRate(), 2);
        }
    }

    /**
     * Get refund amount from refund group transactions
     *
     * @param Order $order
     * @param string|float $baseAmountToRefund
     * @return void
     */
    public function setRefundGroupTransactions(Order $order, $baseAmountToRefund)
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $totalOrder = $order->getBaseGrandTotal();
        $requestParams = $this->request->getParams();

        $this->buckarooLog->addDebug(__METHOD__ . '|20|' . var_export(
            [$baseAmountToRefund, $totalOrder, $baseAmountToRefund >= 0.01],
            true
        ));

        if ($baseAmountToRefund >= 0.01) {
            $groupTransactionAmount = $this->paymentGroupTransaction->getGroupTransactionAmount(
                $order->getIncrementId()
            );

            if (($groupTransactionAmount > 0.01)
                && empty($requestParams['creditmemo']['buckaroo_already_paid'])
                && !empty($requestParams['creditmemo']['adjustment_negative'])
            ) {
                $this->buckarooLog->addDebug(__METHOD__ . '|22|');
                $order->setAdjustmentNegative(0);
            }

            if ($baseAmountToRefund == $order->getBaseGrandTotal() && $groupTransactionAmount > 0) {
                $this->buckarooLog->addDebug(__METHOD__ . '|25|' . var_export($groupTransactionAmount, true));
                $this->payRemainder = $baseAmountToRefund - $groupTransactionAmount;
                $this->refundAmount = $baseAmountToRefund - $groupTransactionAmount;
            }

            if ($baseAmountToRefund > $totalOrder) {
                $this->refundAmount = $totalOrder;
            }
        } else {
            $this->refundAmount = 0;
        }
    }
}
