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

namespace Buckaroo\Magento2\Test\Unit\Service\Transaction;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\Transaction\OutcomeResolver;
use Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OutcomeResolverTest extends TestCase
{
    private const STARTED = '2026-11-23 14:10:00';

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $adapter;

    /**
     * @var int
     */
    private int $sleeps = 0;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(BuckarooAdapter::class);
        $this->sleeps = 0;
    }

    public function testFindsThePaymentCreatedByTheLostRequest(): void
    {
        $this->givenInvoice(['OLD', 'NEW']);
        $this->givenStatuses([
            'OLD' => $this->transaction('OLD', '2026-11-23T14:00:00', ['AmountDebit' => 40.05]),
            'NEW' => $this->transaction('NEW', '2026-11-23T14:10:08', ['AmountDebit' => 40.05]),
        ]);

        $response = $this->resolver()->resolve('pay', 'applepay', $this->payData(), $this->started());

        $this->assertSame('NEW', $response->getTransactionKey());
    }

    public function testIgnoresATransactionWithAnotherAmount(): void
    {
        $this->givenInvoice(['NEW']);
        $this->givenStatuses(['NEW' => $this->transaction('NEW', '2026-11-23T14:10:08', ['AmountDebit' => 10])]);

        $this->assertNull($this->resolver()->resolve('pay', 'applepay', $this->payData(), $this->started()));
        $this->assertSame(2, $this->sleeps, 'looks again before giving up');
    }

    public function testTwoCandidatesAreNotGuessedBetween(): void
    {
        $this->givenInvoice(['A', 'B']);
        $this->givenStatuses([
            'A' => $this->transaction('A', '2026-11-23T14:10:05', ['AmountDebit' => 40.05]),
            'B' => $this->transaction('B', '2026-11-23T14:10:09', ['AmountDebit' => 40.05]),
        ]);

        $this->assertNull($this->resolver()->resolve('pay', 'applepay', $this->payData(), $this->started()));
        $this->assertSame(0, $this->sleeps);
    }

    public function testFindsARefundThroughTheOriginalTransaction(): void
    {
        $this->givenInvoice([]);
        $this->givenStatuses([
            'ORIG'    => $this->transaction('ORIG', '2026-11-23T13:00:00', [
                'AmountDebit'         => 40.05,
                'RelatedTransactions' => [
                    ['RelationType' => 'refund', 'RelatedTransactionKey' => 'REFUND1'],
                    ['RelationType' => 'refund', 'RelatedTransactionKey' => 'REFUND2'],
                ],
            ]),
            'REFUND1' => $this->transaction('REFUND1', '2026-11-23T13:30:00', $this->refundOf('ORIG', 10)),
            'REFUND2' => $this->transaction('REFUND2', '2026-11-23T14:10:03', $this->refundOf('ORIG', 10)),
        ]);

        $response = $this->resolver()->resolve(
            'refund',
            'ideal',
            ['invoice' => '300000501', 'originalTransactionKey' => 'ORIG', 'amountCredit' => 10, 'orderStoreId' => 1],
            $this->started()
        );

        $this->assertSame('REFUND2', $response->getTransactionKey());
    }

    public function testLookupErrorsAreRetriedThenGiveUp(): void
    {
        $this->adapter->method('getTransactionsByInvoice')->willThrowException(new \RuntimeException('down'));

        $this->assertNull($this->resolver()->resolve('pay', 'applepay', $this->payData(), $this->started()));
        $this->assertSame(2, $this->sleeps);
    }

    public function testStopsLookingWhenTheTimeBudgetIsSpent(): void
    {
        $this->adapter->expects($this->once())->method('getTransactionsByInvoice')->willReturn([]);
        $now = 1000;

        $resolver = new OutcomeResolver(
            $this->adapter,
            $this->createMock(BuckarooLoggerInterface::class),
            function () use (&$now) {
                $now += 20;
            },
            function () use (&$now) {
                return $now;
            }
        );

        $this->assertNull($resolver->resolve('pay', 'applepay', $this->payData(), $this->started()));
    }

    public function testStopsCheckingCandidatesOnceTheBudgetIsSpent(): void
    {
        $this->givenInvoice(['A', 'B', 'C']);
        $now = 1000;
        // Every status call takes 10 seconds
        $this->adapter->method('getTransactionStatus')->willReturnCallback(function (...$args) use (&$now) {
            $key = $args[2];
            $now += 10;
            return $this->transaction($key, '2026-11-23T14:10:08', ['AmountDebit' => $key === 'C' ? 40.05 : 1]);
        });

        $resolver = new OutcomeResolver(
            $this->adapter,
            $this->createMock(BuckarooLoggerInterface::class),
            function () {
            },
            function () use (&$now) {
                return $now;
            }
        );

        $this->assertNull($resolver->resolve('pay', 'applepay', $this->payData(), $this->started()));
        $this->assertLessThanOrEqual(1030, $now, 'no candidate is checked after the budget ran out');
    }

    /**
     * @return OutcomeResolver
     */
    private function resolver(): OutcomeResolver
    {
        return new OutcomeResolver(
            $this->adapter,
            $this->createMock(BuckarooLoggerInterface::class),
            function () {
                $this->sleeps++;
            }
        );
    }

    /**
     * @param string[] $keys
     */
    private function givenInvoice(array $keys): void
    {
        $this->adapter->method('getTransactionsByInvoice')
            ->willReturn(array_map(fn($key) => ['key' => $key, 'status' => 190], $keys));
    }

    /**
     * @param TransactionResponse[] $byKey
     */
    private function givenStatuses(array $byKey): void
    {
        $this->adapter->method('getTransactionStatus')
            ->willReturnCallback(fn(...$args) => $byKey[$args[2]]);
    }

    /**
     * @param string $key
     * @param string $dateTime Buckaroo local time
     * @param array  $data
     *
     * @return TransactionResponse
     */
    private function transaction(string $key, string $dateTime, array $data): TransactionResponse
    {
        return new TransactionResponse(null, array_merge([
            'Key'    => $key,
            'Status' => ['Code' => ['Code' => 190], 'DateTime' => $dateTime],
        ], $data));
    }

    /**
     * @param string $originalKey
     * @param float  $amount
     *
     * @return array
     */
    private function refundOf(string $originalKey, float $amount): array
    {
        return [
            'AmountCredit'        => $amount,
            'RelatedTransactions' => [['RelationType' => 'refund', 'RelatedTransactionKey' => $originalKey]],
        ];
    }

    /**
     * @return array
     */
    private function payData(): array
    {
        return ['invoice' => '300000490', 'amountDebit' => 40.05, 'orderStoreId' => 1];
    }

    /**
     * @return int
     */
    private function started(): int
    {
        return (new \DateTimeImmutable(self::STARTED, new \DateTimeZone('Europe/Amsterdam')))->getTimestamp();
    }
}
