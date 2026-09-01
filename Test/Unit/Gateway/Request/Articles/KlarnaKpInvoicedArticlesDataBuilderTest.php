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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles;

/**
 * Buckaroo's Klarna KP Pay documentation takes no prices on a capture: a full
 * capture carries only the reservation number, and a partial capture nominates reserved lines
 * by ArticleNumber and ArticleQuantity. Sending a price Klarna did not reserve is what produced
 * "Sum of given articles (3,36) is not equal to the given amount (19,36)".
 */
class KlarnaKpInvoicedArticlesDataBuilderTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass =
        'Buckaroo\Magento2\Gateway\Request\Articles\KlarnaKpInvoicedArticlesDataBuilder';

    /**
     * A partial capture names the shipped lines and their quantities, nothing else.
     */
    public function testAPartialCaptureNominatesLinesByNumberAndQuantity(): void
    {
        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'description' => 'Voyage Yoga Bag', 'quantity' => 1,
             'price' => 19.36, 'vatPercentage' => 21.0],
        ]];

        $result = $this->buildFor($articles, true, 1);

        $this->assertSame(
            ['articles' => [['identifier' => '24-WB01', 'quantity' => 1]]],
            $result,
            'Only ArticleNumber and ArticleQuantity may be sent'
        );
    }

    /**
     * A full capture carries the reservation number alone.
     */
    public function testAFullCaptureSendsNoArticlesAtAll(): void
    {
        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36],
            ['identifier' => '24-WB02', 'quantity' => 1, 'price' => 19.36],
        ]];

        $this->assertSame([], $this->buildFor($articles, false, 1));
    }

    /**
     * A later capture is still partial even though nothing remains to invoice afterwards.
     */
    public function testTheFinalCaptureOfSeveralIsStillNominatedByLine(): void
    {
        $articles = ['articles' => [
            ['identifier' => '24-WB02', 'quantity' => 2, 'price' => 19.36],
        ]];

        $this->assertSame(
            ['articles' => [['identifier' => '24-WB02', 'quantity' => 2]]],
            $this->buildFor($articles, false, 2)
        );
    }

    /**
     * AmountDebit still comes from the priced lines, so it matches what Klarna computes for the
     * nominated lines.
     */
    public function testTheAmountIsRegisteredFromThePricedLines(): void
    {
        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36],
        ]];

        $registry = $this->getFakeMock('Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry')
            ->getMock();
        $registry->method('sumArticles')->willReturn(19.36);
        $registry->expects($this->once())
            ->method('set')
            ->with(
                \Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry::CONTEXT_INVOICE,
                '000000009',
                19.36
            );

        $this->buildFor($articles, true, 1, $registry);
    }

    public function testMalformedLinesAreDropped(): void
    {
        $articles = ['articles' => [
            ['description' => 'no identifier', 'quantity' => 1],
            'not an array',
            ['identifier' => '24-WB01', 'quantity' => 3],
        ]];

        $this->assertSame(
            ['articles' => [['identifier' => '24-WB01', 'quantity' => 3]]],
            $this->buildFor($articles, true, 1)
        );
    }

    /**
     * @param array       $articles
     * @param bool        $canInvoice
     * @param int         $invoiceCount
     * @param object|null $registry
     *
     * @return array
     */
    private function buildFor(array $articles, bool $canInvoice, int $invoiceCount, $registry = null): array
    {
        $handler = $this->getFakeMock(
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler'
        )->getMock();
        $handler->method('getInvoiceArticlesData')->willReturn($articles);

        $handlerFactory = $this->getFakeMock(
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory'
        )->getMock();
        $handlerFactory->method('create')->willReturn($handler);

        if ($registry === null) {
            $registry = $this->getFakeMock(
                'Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry'
            )->getMock();
            $registry->method('sumArticles')->willReturn(0.0);
        }

        $collection = $this->getFakeMock('Magento\Sales\Model\ResourceModel\Order\Invoice\Collection')
            ->getMock();
        $collection->method('count')->willReturn($invoiceCount);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getInvoiceCollection')->willReturn($collection);
        $order->method('canInvoice')->willReturn($canInvoice);
        $order->method('getIncrementId')->willReturn('000000009');

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $payment->method('getOrder')->willReturn($order);

        $orderAdapter = $this->getFakeMock('Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter')->getMock();
        $orderAdapter->method('getOrder')->willReturn($order);

        $paymentDO = $this->getFakeMock('Magento\Payment\Gateway\Data\PaymentDataObjectInterface')
            ->getMock();
        $paymentDO->method('getPayment')->willReturn($payment);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);

        $instance = $this->getInstance([
            'articlesHandlerFactory' => $handlerFactory,
            'articleTotalRegistry' => $registry,
        ]);

        return $instance->build(['payment' => $paymentDO]);
    }
}
