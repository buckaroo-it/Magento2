<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\DataBuilderService;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AmountCreditDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    private const AMOUNT_CREDIT = 'amountCredit';

    /**
     * @var float
     */
    public $refundAmount;

    /**
     * @var DataBuilderService
     */
    private DataBuilderService $dataBuilderService;

    /**
     * @var RefundGroupTransactionService
     */
    private RefundGroupTransactionService $refundGroupService;

    /**
     * Constructor
     *
     * @param DataBuilderService $dataBuilderService
     * @param RefundGroupTransactionService $refundGroupService
     */
    public function __construct(
        DataBuilderService                      $dataBuilderService,
        RefundGroupTransactionService           $refundGroupService
    ) {
        $this->dataBuilderService = $dataBuilderService;
        $this->refundGroupService = $refundGroupService;
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

        if ($this->refundAmount <= 0) {
            throw new \InvalidArgumentException('Credit Amount less than or equal to 0');
        }

        $this->refundGroupService->refundGroupTransactions($buildSubject);
        $this->refundAmount = $this->refundGroupService->getAmountLeftToRefund();

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
}
