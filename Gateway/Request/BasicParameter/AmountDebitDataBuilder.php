<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry;
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
     * @var ArticleTotalRegistry
     */
    private ArticleTotalRegistry $articleTotalRegistry;

    /**
     * @param ArticleTotalRegistry $articleTotalRegistry
     */
    public function __construct(ArticleTotalRegistry $articleTotalRegistry)
    {
        $this->articleTotalRegistry = $articleTotalRegistry;
    }

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
     * Get the amount to send in the order currency.
     *
     * @param Order $order
     *
     * @return float|null
     */
    public function getAmount(Order $order): ?float
    {
        $articleTotal = $this->articleTotalRegistry->get(
            ArticleTotalRegistry::CONTEXT_ORDER,
            (string)$order->getIncrementId()
        );
        if ($articleTotal !== null) {
            return $articleTotal;
        }

        $total = $order->getGrandTotal();

        return $total === null ? null : (float)$total;
    }
}
