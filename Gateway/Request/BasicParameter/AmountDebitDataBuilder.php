<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\DataBuilderService;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AmountDebitDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    private const AMOUNT_DEBIT = 'amountDebit';

    /**
     * @var float
     */
    private $amount;

    /**
     * @var DataBuilderService
     */
    private DataBuilderService $dataBuilderService;

    /**
     * Constructor
     *
     * @param DataBuilderService $dataBuilderService
     */
    public function __construct(
        DataBuilderService $dataBuilderService
    ) {
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

        return [
            self::AMOUNT_DEBIT => $this->getAmount($order)
        ];
    }

    /**
     * Get Amount
     *
     * @param Order|null $order
     * @return string|float
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
        if ($this->dataBuilderService->getElement('currency') == $order->getOrderCurrencyCode()) {
            $this->amount = $order->getGrandTotal();
        }
        $this->amount = $order->getBaseGrandTotal();

        return $this;
    }
}
