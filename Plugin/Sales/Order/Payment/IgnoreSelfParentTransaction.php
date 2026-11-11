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

namespace Buckaroo\Magento2\Plugin\Sales\Order\Payment;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * A transaction is never its own parent.
 *
 * Magento 2.4.8 can store the authorization as its own parent (ACP2E-4601, fixed in 2.4.9).
 * DetachSelfParentedTransaction removes that link when the transaction is saved, but a row stored
 * that way earlier stays as it is until then. Closing the authorization closes its parent first,
 * so on cancel such a row closed itself until PHP ran out of memory.
 */
class IgnoreSelfParentTransaction
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return no parent when the parent is the transaction itself.
     *
     * @param Transaction       $subject
     * @param Transaction|false $result
     *
     * @return Transaction|false
     */
    public function afterGetParentTransaction(Transaction $subject, $result)
    {
        $transactionId = (int)$subject->getId();
        if (!$result instanceof Transaction || $transactionId <= 0 || (int)$result->getId() !== $transactionId) {
            return $result;
        }

        $this->logger->addDebug(sprintf(
            '[TRANSACTION] | [%s:%s] - Ignored the self-referencing parent of %s transaction %s.',
            __METHOD__,
            __LINE__,
            (string)$subject->getTxnType(),
            (string)$subject->getTxnId()
        ));

        return false;
    }
}
