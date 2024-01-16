<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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
