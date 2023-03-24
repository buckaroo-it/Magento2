<?php

namespace Buckaroo\Magento2\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Command\SkipCommandInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class GiftcardOrderSkip implements SkipCommandInterface
{
    /**
     * @var PaymentGroupTransaction
     */
    protected $paymentGroupTransaction;

    /**
     * @param PaymentGroupTransaction $paymentGroupTransaction
     */
    public function __construct(PaymentGroupTransaction $paymentGroupTransaction)
    {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
    }

    /**
     * @inheritDoc
     */
    public function isSkip(array $commandSubject): bool
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $orderIncrementId = $paymentDO->getPayment()->getOrder()->getIncrementId();
        return $this->paymentGroupTransaction->isGroupTransaction($orderIncrementId);
    }
}
