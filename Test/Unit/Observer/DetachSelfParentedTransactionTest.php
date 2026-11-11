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

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Observer\DetachSelfParentedTransaction;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * A transaction is never its own parent; when Magento saves one that way, closing it loops
 * forever, so the link is removed straight after the save.
 */
class DetachSelfParentedTransactionTest extends TestCase
{
    public function testASelfParentedBuckarooTransactionLosesItsParent(): void
    {
        $transaction = $this->makeTransaction(517, 517);
        $connection = $this->makeConnection('buckaroo_magento2_klarnakp');
        $connection->expects($this->once())->method('update')->with(
            'sales_payment_transaction',
            ['parent_id' => null],
            ['transaction_id = ?' => 517, 'parent_id = ?' => 517]
        );

        $this->makeObserver($connection)->execute($this->eventFor($transaction));

        $this->assertNull($transaction->getData('parent_id'));
    }

    public function testATransactionWithARealParentIsLeftAlone(): void
    {
        $transaction = $this->makeTransaction(518, 517);
        $connection = $this->makeConnection('buckaroo_magento2_klarnakp');
        $connection->expects($this->never())->method('update');

        $this->makeObserver($connection)->execute($this->eventFor($transaction));

        $this->assertSame(517, $transaction->getData('parent_id'));
    }

    public function testAnotherProvidersTransactionIsLeftAlone(): void
    {
        $transaction = $this->makeTransaction(600, 600);
        $connection = $this->makeConnection('mollie_methods_klarna');
        $connection->expects($this->never())->method('update');

        $this->makeObserver($connection)->execute($this->eventFor($transaction));

        $this->assertSame(600, $transaction->getData('parent_id'));
    }

    /**
     * @param int $id
     * @param int $parentId
     *
     * @return Transaction
     */
    private function makeTransaction(int $id, int $parentId): Transaction
    {
        $transaction = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $transaction->setData([
            'transaction_id' => $id,
            'parent_id' => $parentId,
            'payment_id' => 42,
            'txn_type' => 'authorization',
            'txn_id' => 'F9EC93A2383B444AA1C942FE03FA0726',
        ]);
        $transaction->setId($id);

        return $transaction;
    }

    /**
     * @param string $method
     *
     * @return AdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeConnection(string $method)
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn($method);

        return $connection;
    }

    /**
     * @param AdapterInterface $connection
     *
     * @return DetachSelfParentedTransaction
     */
    private function makeObserver($connection): DetachSelfParentedTransaction
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new DetachSelfParentedTransaction($resource, $this->createMock(BuckarooLoggerInterface::class));
    }

    /**
     * @param Transaction $transaction
     *
     * @return Observer
     */
    private function eventFor(Transaction $transaction): Observer
    {
        return new Observer(['event' => new Event(['order_payment_transaction' => $transaction])]);
    }
}
