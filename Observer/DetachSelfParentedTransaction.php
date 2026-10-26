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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Remove the parent link of a transaction that was saved as its own parent.
 *
 * Magento 2.4.8 can save the authorization as its own parent (ACP2E-4601, fixed in 2.4.9).
 * Closing such a transaction never ends and runs out of memory on cancel.
 */
class DetachSelfParentedTransaction implements ObserverInterface
{
    /**
     * Prefix every payment method this module registers carries.
     */
    private const METHOD_CODE_PREFIX = 'buckaroo_magento2_';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param ResourceConnection      $resourceConnection
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(ResourceConnection $resourceConnection, BuckarooLoggerInterface $logger)
    {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Clear the parent of a Buckaroo transaction that points to itself.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $transaction = $observer->getEvent()->getData('order_payment_transaction');
        if (!$transaction instanceof Transaction) {
            return;
        }

        $transactionId = (int)$transaction->getId();
        if ($transactionId <= 0 || (int)$transaction->getParentId() !== $transactionId) {
            return;
        }

        if (!$this->isBuckarooPayment((int)$transaction->getPaymentId())) {
            return;
        }

        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName('sales_payment_transaction'),
            ['parent_id' => null],
            ['transaction_id = ?' => $transactionId, 'parent_id = ?' => $transactionId]
        );
        $transaction->setData('parent_id', null);

        $this->logger->addDebug(sprintf(
            '[TRANSACTION] | [%s:%s] - Cleared the self-referencing parent of %s transaction %s.',
            __METHOD__,
            __LINE__,
            (string)$transaction->getTxnType(),
            (string)$transaction->getTxnId()
        ));
    }

    /**
     * Whether the payment belongs to a Buckaroo method.
     *
     * @param int $paymentId
     *
     * @return bool
     */
    private function isBuckarooPayment(int $paymentId): bool
    {
        if ($paymentId <= 0) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $method = (string)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('sales_order_payment'), ['method'])
                ->where('entity_id = ?', $paymentId)
        );

        return strpos($method, self::METHOD_CODE_PREFIX) === 0;
    }
}
