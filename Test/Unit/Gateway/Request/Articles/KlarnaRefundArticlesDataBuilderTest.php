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

    public function testARefundWithoutLinesIsRefundedByAmountOnly(): void
    {
        $this->assertSame([], $this->buildFor([], 10.00));
    }

    public function testNoLinesAreSentWithoutAnAmountCredit(): void
    {
        $lines = [['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36]];

        $this->assertSame([], $this->buildFor($lines, null));
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
     * @param array      $lines
     * @param float|null $amountCredit
     *
     * @return array
     */
    private function buildFor(array $lines, ?float $amountCredit): array
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

        $instance = $this->getInstance([
            'articlesHandlerFactory' => $handlerFactory,
            'dataBuilderService' => $dataBuilderService,
        ]);

        return $instance->build(['payment' => $paymentDO]);
    }
}
