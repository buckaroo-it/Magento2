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

namespace Buckaroo\Magento2\Test\Unit\Service\Refund;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Refund\RefundCapResolver;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefundCapResolverTest extends TestCase
{
    /**
     * @var ArticlesHandlerFactory|MockObject
     */
    private $articlesHandlerFactoryMock;

    /**
     * @var RefundCapResolver
     */
    private $resolver;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    protected function setUp(): void
    {
        $this->articlesHandlerFactoryMock = $this->createMock(ArticlesHandlerFactory::class);
        $this->orderMock = $this->createMock(Order::class);

        $this->resolver = new RefundCapResolver(
            $this->articlesHandlerFactoryMock,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    /**
     * A payment that is not an order payment carries no credit memo, so nothing can be capped.
     */
    public function testReturnsRequestedAmountWhenPaymentCarriesNoCreditmemo(): void
    {
        $this->articlesHandlerFactoryMock->expects($this->never())->method('create');

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->createMock(InfoInterface::class),
            42.50
        );

        $this->assertEquals(42.50, $amount);
    }

    /**
     * A memo spanning the whole order has no single invoice to price the capture against.
     */
    public function testReturnsRequestedAmountWhenCreditmemoHasNoInvoice(): void
    {
        $this->articlesHandlerFactoryMock->expects($this->never())->method('create');

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->getPaymentMock($this->getCreditmemoMock(null)),
            42.50
        );

        $this->assertEquals(42.50, $amount);
    }

    /**
     * Magento rounds the memo per invoice while the capture went at reserved prices, so the memo
     * can ask for a cent more than the transaction took. The gateway refuses that, so it is capped.
     */
    public function testLowersTheAmountToWhatTheCaptureTook(): void
    {
        $this->prepareCapturedTotal(100.00);

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->getPaymentMock($this->getCreditmemoMock($this->getInvoiceMock(0.00))),
            100.01
        );

        $this->assertEquals(100.00, $amount);
    }

    /**
     * What an earlier memo already refunded is no longer refundable on the same transaction.
     */
    public function testSubtractsWhatWasAlreadyRefundedFromTheInvoice(): void
    {
        $this->prepareCapturedTotal(100.00);

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->getPaymentMock($this->getCreditmemoMock($this->getInvoiceMock(60.00))),
            50.00
        );

        $this->assertEquals(40.00, $amount);
    }

    /**
     * The cap is a ceiling, never a floor: a memo below the captured total is sent unchanged.
     */
    public function testLeavesAnAmountBelowTheCaptureUntouched(): void
    {
        $this->prepareCapturedTotal(100.00);

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->getPaymentMock($this->getCreditmemoMock($this->getInvoiceMock(0.00))),
            25.00
        );

        $this->assertEquals(25.00, $amount);
    }

    /**
     * The cap is a safety net; a handler that blows up must not block the refund itself.
     */
    public function testReturnsRequestedAmountWhenTheArticlesHandlerFails(): void
    {
        $this->articlesHandlerFactoryMock->method('create')
            ->willThrowException(new \RuntimeException('no handler'));

        $amount = $this->resolver->resolveCappedAmount(
            $this->orderMock,
            $this->getPaymentMock($this->getCreditmemoMock($this->getInvoiceMock(0.00))),
            100.01
        );

        $this->assertEquals(100.01, $amount);
    }

    /**
     * Wire the factory to a handler reporting the given captured total.
     *
     * @param float $captured
     *
     * @return void
     */
    private function prepareCapturedTotal(float $captured): void
    {
        $handlerMock = $this->createMock(AbstractArticlesHandler::class);
        $handlerMock->method('getCapturedTotalForInvoice')->willReturn($captured);

        $this->articlesHandlerFactoryMock->method('create')->willReturn($handlerMock);
    }

    /**
     * @param float $totalRefunded
     *
     * @return Invoice|MockObject
     */
    private function getInvoiceMock(float $totalRefunded)
    {
        // getTotalRefunded() is a DataObject magic getter, so it must be declared on the mock.
        $invoiceMock = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIncrementId', 'getGrandTotal'])
            ->addMethods(['getTotalRefunded'])
            ->getMock();
        $invoiceMock->method('getId')->willReturn(7);
        $invoiceMock->method('getIncrementId')->willReturn('100000007');
        $invoiceMock->method('getGrandTotal')->willReturn(100.01);
        $invoiceMock->method('getTotalRefunded')->willReturn($totalRefunded);


        return $invoiceMock;
    }

    /**
     * @param Invoice|MockObject|null $invoice
     *
     * @return Creditmemo|MockObject
     */
    private function getCreditmemoMock($invoice)
    {
        $creditmemoMock = $this->createMock(Creditmemo::class);
        $creditmemoMock->method('getInvoice')->willReturn($invoice);

        return $creditmemoMock;
    }

    /**
     * @param Creditmemo|MockObject $creditmemo
     *
     * @return Payment|MockObject
     */
    private function getPaymentMock($creditmemo)
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getCreditmemo')->willReturn($creditmemo);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');

        return $paymentMock;
    }
}
