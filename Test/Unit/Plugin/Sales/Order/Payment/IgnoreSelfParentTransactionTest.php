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

namespace Buckaroo\Magento2\Test\Unit\Plugin\Sales\Order\Payment;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Plugin\Sales\Order\Payment\IgnoreSelfParentTransaction;
use Magento\Sales\Model\Order\Payment\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * A transaction stored as its own parent has no parent, so closing it ends.
 */
class IgnoreSelfParentTransactionTest extends TestCase
{
    public function testATransactionThatIsItsOwnParentHasNoParent(): void
    {
        $plugin = new IgnoreSelfParentTransaction($this->createMock(BuckarooLoggerInterface::class));

        $this->assertFalse($plugin->afterGetParentTransaction($this->transaction(469), $this->transaction(469)));
    }

    public function testAnotherParentIsKept(): void
    {
        $plugin = new IgnoreSelfParentTransaction($this->createMock(BuckarooLoggerInterface::class));
        $parent = $this->transaction(468);

        $this->assertSame($parent, $plugin->afterGetParentTransaction($this->transaction(469), $parent));
    }

    public function testNoParentStaysNoParent(): void
    {
        $plugin = new IgnoreSelfParentTransaction($this->createMock(BuckarooLoggerInterface::class));

        $this->assertFalse($plugin->afterGetParentTransaction($this->transaction(469), false));
    }

    public function testAnUnsavedTransactionKeepsWhatItGot(): void
    {
        $plugin = new IgnoreSelfParentTransaction($this->createMock(BuckarooLoggerInterface::class));
        $parent = $this->transaction(0);

        $this->assertSame($parent, $plugin->afterGetParentTransaction($this->transaction(0), $parent));
    }

    /**
     * @param int $id
     *
     * @return Transaction
     */
    private function transaction(int $id)
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getId')->willReturn($id ?: null);

        return $transaction;
    }
}
