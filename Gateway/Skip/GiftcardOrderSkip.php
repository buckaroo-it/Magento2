<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

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
     * @inheritdoc
     */
    public function isSkip(array $commandSubject): bool
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $orderIncrementId = $paymentDO->getPayment()->getOrder()->getIncrementId();
        return $this->paymentGroupTransaction->isAnyGroupTransaction($orderIncrementId);
    }
}
