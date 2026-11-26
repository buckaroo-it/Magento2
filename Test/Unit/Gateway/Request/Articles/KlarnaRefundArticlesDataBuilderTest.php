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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles;

use Buckaroo\Magento2\Service\DataBuilderService;

/**
 * A Klarna refund names the refunded order lines by ArticleNumber and ArticleQuantity, so Klarna
 * registers which lines were refunded. A refund the lines do not explain stays a refund by amount.
 */
class KlarnaRefundArticlesDataBuilderTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass =
        'Buckaroo\Magento2\Gateway\Request\Articles\KlarnaRefundArticlesDataBuilder';

    public function testARefundOfLinesNominatesThemByNumberAndQuantity(): void
    {
        $lines = [
            ['identifier' => '24-WB01', 'quantity' => 2, 'price' => 19.36],
            ['identifier' => '2', 'quantity' => 1, 'price' => 4.95],
        ];

        $this->assertSame(
            ['articles' => [
                ['identifier' => '24-WB01', 'quantity' => 2],
                ['identifier' => '2', 'quantity' => 1],
            ]],
            $this->buildFor($lines, 43.67)
        );
    }

    public function testAnAmountTheLinesDoNotExplainIsRefundedByAmountOnly(): void
    {
        $lines = [['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36]];

        // An adjustment refund on top of the line
        $this->assertSame([], $this->buildFor($lines, 24.36));
    }

    public function testOneCentMoreThanTheLinesIsRefundedByAmountOnly(): void
    {
        $lines = [['identifier' => 'A', 'quantity' => 1, 'price' => 10.00]];

        $this->assertSame([], $this->buildFor($lines, 10.01));
    }

    public function testOneCentLessThanTheLinesIsRefundedByAmountOnly(): void
    {
        $lines = [['identifier' => 'A', 'quantity' => 1, 'price' => 10.01]];

        $this->assertSame([], $this->buildFor($lines, 10.00));
    }

    /**
     * A refund without lines needs no explaining, so nothing is logged for it.
     */
    public function testARefundWithoutLinesIsRefundedByAmountOnlyWithoutALogLine(): void
    {
        $logger = $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock();
        $logger->expects($this->never())->method('addDebug');

        $this->assertSame([], $this->buildFor([], 10.00, $logger));
    }

    public function testNoLinesAreSentWithoutAnAmountCredit(): void
    {
        $lines = [['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36]];

        $this->assertSame([], $this->buildFor($lines, null));
    }

    /**
     * A zero refund (a group transaction refunded every leg itself) is never sent with lines,
     * not even lines that are free.
     */
    public function testAZeroAmountCreditSendsNoLinesEvenForFreeLines(): void
    {
        $lines = [['identifier' => 'FREE', 'quantity' => 1, 'price' => 0.0]];

        $this->assertSame([], $this->buildFor($lines, 0.0));
    }

    public function testASingleCentIsEnoughToNominateLines(): void
    {
        $lines = [['identifier' => 'A', 'quantity' => 1, 'price' => 0.01]];

        $this->assertSame(
            ['articles' => [['identifier' => 'A', 'quantity' => 1]]],
            $this->buildFor($lines, 0.01)
        );
    }

    public function testCentRoundingOfTheLineTotalStillMatches(): void
    {
        $lines = [['identifier' => 'A', 'quantity' => 3, 'price' => 0.1]];

        $this->assertSame(
            ['articles' => [['identifier' => 'A', 'quantity' => 3]]],
            $this->buildFor($lines, 0.30)
        );
    }

    /**
     * 0.10 + 0.19 sums to just above 0.29 and 0.29 * 100 lands just below 29: both are 29 cents.
     */
    public function testFloatNoiseOnEitherSideOfACentStillMatches(): void
    {
        $lines = [
            ['identifier' => 'A', 'quantity' => 1, 'price' => 0.1],
            ['identifier' => 'B', 'quantity' => 1, 'price' => 0.19],
        ];

        $this->assertSame(
            ['articles' => [['identifier' => 'A', 'quantity' => 1], ['identifier' => 'B', 'quantity' => 1]]],
            $this->buildFor($lines, 0.29)
        );
    }

    /**
     * @param array       $lines
     * @param float|null  $amountCredit
     * @param object|null $logger
     *
     * @return array
     */
    private function buildFor(array $lines, ?float $amountCredit, $logger = null): array
    {
        $handler = $this->getFakeMock(
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler'
        )->getMock();
        $handler->method('getCreditmemoRefundLines')->willReturn($lines);

        $handlerFactory = $this->getFakeMock(
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory'
        )->getMock();
        $handlerFactory->method('create')->willReturn($handler);

        $dataBuilderService = new DataBuilderService();
        if ($amountCredit !== null) {
            $dataBuilderService->addData(['amountCredit' => $amountCredit]);
        }

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getIncrementId')->willReturn('000000009');

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');

        $orderAdapter = $this->getFakeMock('Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter')->getMock();
        $orderAdapter->method('getOrder')->willReturn($order);

        $paymentDO = $this->getFakeMock('Magento\Payment\Gateway\Data\PaymentDataObjectInterface')
            ->getMock();
        $paymentDO->method('getPayment')->willReturn($payment);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $arguments = [
            'articlesHandlerFactory' => $handlerFactory,
            'dataBuilderService' => $dataBuilderService,
        ];
        if ($logger !== null) {
            $arguments['logger'] = $logger;
        }

        return $this->getInstance($arguments)->build(['payment' => $paymentDO]);
    }
}
