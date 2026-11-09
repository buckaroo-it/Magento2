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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;

use Buckaroo\Magento2\Gateway\Response\GiftCardRemoveHandler;
use Buckaroo\Magento2\Model\Giftcard\RemoveException;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\GroupTransactionRepository;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GiftCardRemoveHandlerTest extends TestCase
{
    /**
     * @var GroupTransactionRepository|MockObject
     */
    private $groupTransactionRepositoryMock;

    /**
     * @var GroupTransaction|MockObject
     */
    private $giftcardTransactionMock;

    /**
     * @var GiftCardRemoveHandler
     */
    private $handler;

    protected function setUp(): void
    {
        $this->groupTransactionRepositoryMock = $this->createMock(GroupTransactionRepository::class);
        $this->giftcardTransactionMock = $this->createMock(GroupTransaction::class);

        $this->handler = new GiftCardRemoveHandler(
            $this->createMock(MessageManager::class),
            $this->groupTransactionRepositoryMock
        );
    }

    /**
     * A refund response reports the refunded amount as AmountCredit and has no AmountDebit.
     */
    public function testRefundResponseWithAmountCreditUpdatesRefundedAmount(): void
    {
        $this->expectRefundedAmountSaved(2.5, 12.5);

        $this->handler->handle(
            ['giftcardTransaction' => $this->giftcardTransactionMock],
            $this->getResponse(['Status' => ['Code' => ['Code' => 190]], 'AmountCredit' => 10.00])
        );
    }

    public function testResponseWithAmountDebitOnlyUpdatesRefundedAmount(): void
    {
        $this->expectRefundedAmountSaved(0.0, 10.0);

        $this->handler->handle(
            ['giftcardTransaction' => $this->giftcardTransactionMock],
            $this->getResponse(['Status' => ['Code' => ['Code' => 190]], 'AmountDebit' => 10.00])
        );
    }

    public function testUnsuccessfulResponseDoesNotUpdateRefundedAmount(): void
    {
        $this->giftcardTransactionMock->expects($this->never())->method('setRefundedAmount');
        $this->groupTransactionRepositoryMock->expects($this->never())->method('save');

        $this->handler->handle(
            ['giftcardTransaction' => $this->giftcardTransactionMock],
            $this->getResponse(['Status' => ['Code' => ['Code' => 490]]])
        );
    }

    public function testAlreadyRemovedGiftcardThrows(): void
    {
        $this->expectException(RemoveException::class);

        $this->handler->handle(
            ['giftcardTransaction' => $this->giftcardTransactionMock],
            $this->getResponse(['Status' => ['Code' => ['Code' => 690]]])
        );
    }

    private function expectRefundedAmountSaved(float $previouslyRefunded, float $expectedRefunded): void
    {
        $this->giftcardTransactionMock->method('getRefundedAmount')->willReturn($previouslyRefunded);
        $this->giftcardTransactionMock->expects($this->once())
            ->method('setRefundedAmount')
            ->with($this->identicalTo($expectedRefunded))
            ->willReturnSelf();
        $this->groupTransactionRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->giftcardTransactionMock);
    }

    private function getResponse(array $data): array
    {
        return ['object' => new TransactionResponse(null, $data)];
    }
}
