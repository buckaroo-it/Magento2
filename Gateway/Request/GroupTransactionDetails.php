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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\GroupTransaction;
use Magento\Payment\Gateway\Request\BuilderInterface;

class GroupTransactionDetails implements BuilderInterface
{
    private const GROUP_TRANSACTION_AMOUNT_CREDIT   = 'amountCredit';
    private const GROUP_TRANSACTION_CURRENCY        = 'currency';
    private const GROUP_TRANSACTION_INVOICE         = 'invoice';
    private const GROUP_TRANSACTION_ORDER           = 'order';
    private const GROUP_TRANSACTION_TRANSACTION_KEY = 'originalTransactionKey';
    private const GROUP_TRANSACTION_SERVICE_CODE    = 'payment_method';
    private const GIFTCARD_NAME                     = 'name';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        if (isset($buildSubject['giftcardTransaction'])
            && $buildSubject['giftcardTransaction'] instanceof GroupTransaction) {
            $giftcardTransaction = $buildSubject['giftcardTransaction'];
            return [
                self::GROUP_TRANSACTION_AMOUNT_CREDIT   => $giftcardTransaction->getRemainingAmount(),
                self::GROUP_TRANSACTION_CURRENCY        => $giftcardTransaction->getCurrency(),
                self::GROUP_TRANSACTION_INVOICE         => $giftcardTransaction->getOrderIncrementId(),
                self::GROUP_TRANSACTION_ORDER           => $giftcardTransaction->getOrderIncrementId(),
                self::GROUP_TRANSACTION_TRANSACTION_KEY => $giftcardTransaction->getTransactionId(),
                self::GROUP_TRANSACTION_SERVICE_CODE    => 'giftcard',
                self::GIFTCARD_NAME                     => $giftcardTransaction->getServicecode()
            ];
        }

        return [];
    }
}
