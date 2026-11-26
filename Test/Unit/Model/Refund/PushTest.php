<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Refund;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
use Buckaroo\Magento2\Model\Refund\Push;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PushTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Push::class;

    /** @var MockObject|CreditmemoFactory */
    private $creditmemoFactoryMock;

    /** @var MockObject|CreditmemoManagementInterface */
    private $creditmemoManagementMock;

    /** @var MockObject|CreditmemoSender */
    private $creditEmailSenderMock;

    /** @var MockObject|Refund */
    private $configRefundMock;

    /** @var MockObject|Data */
    private $helperMock;

    /** @var MockObject|BuckarooLoggerInterface */
    private $loggerMock;

    /** @var MockObject|ScopeConfigInterface */
    private $scopeConfigMock;

    /** @var MockObject|InvoiceService */
    private $invoiceServiceMock;

    /** @var MockObject|Transaction */
    private $transactionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->creditmemoFactoryMock = $this->getFakeMock(CreditmemoFactory::class)->getMock();
        $this->creditmemoManagementMock = $this->getFakeMock(CreditmemoManagementInterface::class)->getMock();
        $this->creditEmailSenderMock = $this->getFakeMock(CreditmemoSender::class)->getMock();
        $this->configRefundMock = $this->getFakeMock(Refund::class)->getMock();
        $this->helperMock = $this->getFakeMock(Data::class)->getMock();
        $this->loggerMock = $this->getFakeMock(BuckarooLoggerInterface::class)->getMock();
        $this->scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
        $this->invoiceServiceMock = $this->getFakeMock(InvoiceService::class)->getMock();
        $this->transactionMock = $this->getFakeMock(Transaction::class)->getMock();

        // Plain amount comparison to the cent, so the full/adjustment decision follows the amounts.
        $this->helperMock->method('areEqualAmounts')->willReturnCallback(
            static function ($first, $second) {
                return abs((float)$first - (float)$second) < 0.005;
            }
        );
    }

    public function getInstance(array $args = []): Push
    {
        return parent::getInstance([
            'creditmemoFactory' => $this->creditmemoFactoryMock,
            'creditmemoManagement' => $this->creditmemoManagementMock,
            'creditEmailSender' => $this->creditEmailSenderMock,
            'configRefund' => $this->configRefundMock,
            'helper' => $this->helperMock,
            'logger' => $this->loggerMock,
            'scopeConfig' => $this->scopeConfigMock,
            'invoiceService' => $this->invoiceServiceMock,
            'transaction' => $this->transactionMock,
        ] + $args);
    }

    /**
     * Successful push from Plaza (status 190) in the order's base currency.
     *
     * @param float $amountCredit
     * @return MockObject|PushRequestInterfaceStub
     */
    private function getPostDataMock(float $amountCredit)
    {
        $postDataMock = $this->createMock(PushRequestInterfaceStub::class);
        $postDataMock->method('getTransactions')->willReturn('trans123');
        $postDataMock->method('getCurrency')->willReturn('EUR');
        $postDataMock->method('getTransactionMethod')->willReturn('ideal');
        $postDataMock->method('getTransactionType')->willReturn('C121');
        $postDataMock->method('getAmountCredit')->willReturn($amountCredit);
        $postDataMock->method('getStatusCode')->willReturn('190');
        $postDataMock->method('getStatusMessage')->willReturn('Success');

        return $postDataMock;
    }

    /**
     * Order of 133.50: subtotal 100, shipping 10 (excl. tax), tax 21, Buckaroo fee 2.00 + 0.50 tax.
     * Two lines: item 7 (2 invoiced) and item 8 (1 invoiced).
     *
     * @param Payment $payment
     * @param array $previousCreditmemos
     * @param float $totalRefunded
     * @param float $adjustmentPositiveRefunded
     * @return MockObject|OrderStub
     */
    private function getOrderMock(
        Payment $payment,
        array $previousCreditmemos = [],
        float $totalRefunded = 0.0,
        float $adjustmentPositiveRefunded = 0.0
    ) {
        $collectionMock = $this->getFakeMock(CreditmemoCollection::class)
            ->onlyMethods(['getItemsByColumnValue', 'getIterator'])
            ->getMock();
        $collectionMock->method('getItemsByColumnValue')->with('transaction_id', 'trans123')->willReturn([]);
        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator($previousCreditmemos));

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getState')->willReturn(Order::STATE_COMPLETE);
        $orderMock->method('canCreditmemo')->willReturn(true);
        $orderMock->method('hasInvoices')->willReturn(true);
        $orderMock->method('getCreditmemosCollection')->willReturn($collectionMock);
        $orderMock->method('getPayment')->willReturn($payment);
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getBaseToOrderRate')->willReturn(1.0);
        $orderMock->method('getBaseGrandTotal')->willReturn(133.50);
        $orderMock->method('getBaseTotalRefunded')->willReturn($totalRefunded);
        $orderMock->method('getBaseSubtotal')->willReturn(100.0);
        $orderMock->method('getBaseSubtotalRefunded')->willReturn(0.0);
        $orderMock->method('getBaseShippingAmount')->willReturn(10.0);
        $orderMock->method('getBaseShippingRefunded')->willReturn(0.0);
        $orderMock->method('getBaseTaxAmount')->willReturn(21.0);
        $orderMock->method('getBaseTaxRefunded')->willReturn(0.0);
        $orderMock->method('getBaseDiscountAmount')->willReturn(0.0);
        $orderMock->method('getBaseDiscountRefunded')->willReturn(0.0);
        $orderMock->method('getBaseAdjustmentPositive')->willReturn($adjustmentPositiveRefunded);
        $orderMock->method('getBaseAdjustmentNegative')->willReturn(0.0);
        $orderMock->method('getBaseBuckarooFeeInvoiced')->willReturn(2.0);
        $orderMock->method('getBuckarooFeeBaseTaxAmountInvoiced')->willReturn(0.5);
        $orderMock->method('getAllItems')->willReturn([
            $this->getOrderItemMock(7, 2.0),
            $this->getOrderItemMock(8, 1.0),
        ]);

        return $orderMock;
    }

    /**
     * @param int $itemId
     * @param float $qtyInvoiced
     * @return MockObject|OrderItem
     */
    private function getOrderItemMock(int $itemId, float $qtyInvoiced)
    {
        $itemMock = $this->getFakeMock(OrderItem::class)->getMock();
        $itemMock->method('getId')->willReturn($itemId);
        $itemMock->method('getQtyInvoiced')->willReturn($qtyInvoiced);
        $itemMock->method('getQtyRefunded')->willReturn(0.0);

        return $itemMock;
    }

    /**
     * @return MockObject|Payment
     */
    private function getPaymentMock()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);
        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('buckaroo_refund_transaction_key', 'trans123');

        return $paymentMock;
    }

    /**
     * @return MockObject|Creditmemo
     */
    private function getCreditmemoMock()
    {
        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemoMock->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->method('getAllItems')->willReturn([]);
        $creditmemoMock->expects($this->once())->method('setTransactionId')->with('trans123')->willReturnSelf();
        $creditmemoMock->expects($this->never())->method('setBaseGrandTotal');

        return $creditmemoMock;
    }

    /**
     * Expects the credit memo to be refunded online, without notification, and the credit memo email sent.
     *
     * @param Creditmemo $creditmemo
     */
    private function expectRefundAndEmail(Creditmemo $creditmemo): void
    {
        $this->creditmemoManagementMock->expects($this->once())
            ->method('refund')
            ->with($this->identicalTo($creditmemo), false, false)
            ->willReturn($creditmemo);

        $this->creditEmailSenderMock->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($creditmemo))
            ->willReturn(true);
    }

    /**
     * Push for the remaining 113.50 after an earlier 20.00 adjustment refund on a 133.50 order:
     * together they make the grand total, so shipping, items and the earlier adjustment are credited.
     */
    public function testReceiveRefundPushForRemainingGrandTotalCreditsShippingItemsAndAdjustments()
    {
        $previousCreditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $previousCreditmemo->method('getBaseAdjustmentPositive')->willReturn(20.0);
        $previousCreditmemo->method('getBaseAdjustmentNegative')->willReturn(0.0);

        $orderMock = $this->getOrderMock($this->getPaymentMock(), [$previousCreditmemo], 20.0, 20.0);
        $this->configRefundMock->method('getAllowPush')->with(1)->willReturn(true);
        $this->scopeConfigMock->method('getValue')->willReturn(null);

        $creditmemoMock = $this->getCreditmemoMock();
        $capturedData = null;
        $this->creditmemoFactoryMock->expects($this->once())
            ->method('createByOrder')
            ->with(
                $this->identicalTo($orderMock),
                $this->callback(function ($data) use (&$capturedData) {
                    $capturedData = $data;
                    return true;
                })
            )
            ->willReturn($creditmemoMock);
        $this->expectRefundAndEmail($creditmemoMock);

        $result = $this->getInstance()->receiveRefundPush($this->getPostDataMock(113.50), true, $orderMock);

        $this->assertTrue($result);
        // Shipping 10 + items 100 + tax 21 + fee 2.50 - earlier adjustment 20 = 113.50
        $this->assertEquals(
            [
                'do_offline' => '0',
                'do_refund' => '0',
                'comment_text' => ' ',
                'shipping_amount' => 10.0,
                'adjustment_negative' => 20.0,
                'adjustment_positive' => 2.5,
                'items' => [7 => ['qty' => 2], 8 => ['qty' => 1]],
                'qtys' => [7 => 2, 8 => 1],
            ],
            $capturedData
        );
    }

    /**
     * Partial push of 40.00 on a 133.50 order with nothing refunded yet: no items or shipping,
     * the amount minus the invoiced Buckaroo fee (2.00 + 0.50 tax) becomes a positive adjustment.
     */
    public function testReceiveRefundPushForPartialAmountCreatesAdjustmentRefund()
    {
        $orderMock = $this->getOrderMock($this->getPaymentMock());
        $this->configRefundMock->method('getAllowPush')->with(1)->willReturn(true);

        $creditmemoMock = $this->getCreditmemoMock();
        $capturedData = null;
        $this->creditmemoFactoryMock->expects($this->once())
            ->method('createByOrder')
            ->with(
                $this->identicalTo($orderMock),
                $this->callback(function ($data) use (&$capturedData) {
                    $capturedData = $data;
                    return true;
                })
            )
            ->willReturn($creditmemoMock);
        $this->expectRefundAndEmail($creditmemoMock);

        $result = $this->getInstance()->receiveRefundPush($this->getPostDataMock(40.0), true, $orderMock);

        $this->assertTrue($result);
        $this->assertEquals(
            [
                'do_offline' => '0',
                'do_refund' => '0',
                'comment_text' => ' ',
                'shipping_amount' => '0',
                'adjustment_negative' => '0',
                'adjustment_positive' => 37.5,
                'items' => [7 => ['qty' => 0], 8 => ['qty' => 0]],
                'qtys' => [7 => 0, 8 => 0],
            ],
            $capturedData
        );
    }

    public function testReceiveRefundPushDisabled()
    {
        $postDataMock = $this->createMock(PushRequestInterface::class);
        $postDataMock->method('getTransactions')->willReturn('trans123');

        $creditmemoCollectionMock = $this->getFakeMock(CreditmemoCollection::class)->getMock();
        $creditmemoCollectionMock->method('getItemsByColumnValue')->willReturn([]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(false);
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');
        $this->creditmemoManagementMock->expects($this->never())->method('refund');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund is disabled');

        $instance->receiveRefundPush($postDataMock, true, $orderMock);
    }

    public function testReceiveRefundPushExistingCreditmemo()
    {
        $postDataMock = $this->createMock(PushRequestInterface::class);
        $postDataMock->method('getTransactions')->willReturn('trans123');

        $creditmemoCollectionMock = $this->getFakeMock(CreditmemoCollection::class)->getMock();
        $creditmemoCollectionMock->method('getItemsByColumnValue')
            ->with('transaction_id', 'trans123')
            ->willReturn([new \stdClass()]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(true);
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');
        $this->creditmemoManagementMock->expects($this->never())->method('refund');
        $this->creditEmailSenderMock->expects($this->never())->method('send');

        $instance = $this->getInstance();

        $result = $instance->receiveRefundPush($postDataMock, true, $orderMock);

        $this->assertFalse($result);
    }

    /**
     * A refund made from a Magento credit memo already has its credit memo; its push is
     * acknowledged even when refunds from Plaza are not accepted.
     */
    public function testReceiveRefundPushForExistingCreditmemoIsAcknowledgedWhenPlazaRefundsAreDisabled()
    {
        $postDataMock = $this->createMock(PushRequestInterface::class);
        $postDataMock->method('getTransactions')->willReturn('trans123');

        $creditmemoCollectionMock = $this->getFakeMock(CreditmemoCollection::class)->getMock();
        $creditmemoCollectionMock->method('getItemsByColumnValue')
            ->with('transaction_id', 'trans123')
            ->willReturn([new \stdClass()]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(false);
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');

        $result = $this->getInstance()->receiveRefundPush($postDataMock, true, $orderMock);

        $this->assertFalse($result);
    }

    /**
     * A push without a transaction key must not match credit memos that have no transaction id.
     */
    public function testReceiveRefundPushWithoutTransactionKeyIsNotTreatedAsAlreadyRefunded()
    {
        $postDataMock = $this->createMock(PushRequestInterface::class);
        $postDataMock->method('getTransactions')->willReturn('');

        $creditmemoCollectionMock = $this->getFakeMock(CreditmemoCollection::class)->getMock();
        $creditmemoCollectionMock->expects($this->never())->method('getItemsByColumnValue');

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(false);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund is disabled');

        $this->getInstance()->receiveRefundPush($postDataMock, true, $orderMock);
    }

    public function testReceiveRefundPushInvalidSignature()
    {
        $postDataMock = $this->createMock(PushRequestInterface::class);
        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('canCreditmemo')->willReturn(false);

        $this->configRefundMock->method('getAllowPush')->willReturn(true);
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund push validation failed');

        $instance->receiveRefundPush($postDataMock, false, $orderMock);
    }

    /**
     * When the credit memo cannot be built, createCreditmemo() fails loudly and refunds nothing.
     */
    public function testCreateCreditmemoThrowsAndRefundsNothingWhenCreditmemoCannotBeBuilt()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);
        $paymentMock->expects($this->never())->method('setAdditionalInformation');

        $this->creditmemoFactoryMock->expects($this->once())
            ->method('createByOrder')
            ->willThrowException(new LocalizedException(__('Cannot create creditmemo')));
        $this->creditmemoManagementMock->expects($this->never())->method('refund');
        $this->creditEmailSenderMock->expects($this->never())->method('send');

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(40.0);
        $instance->order = $this->getOrderMock($paymentMock);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Failed to create the creditmemo');

        $instance->createCreditmemo();
    }

    /**
     * A fully refunded (closed, invoiced) order gets no extra invoice, no refund and no
     * credit memo email, and the push is reported as handled.
     */
    public function testCreateCreditmemoSkipsRefundWhenOrderCanNoLongerBeCredited()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $orderMock = $this->getFakeMock(OrderStub::class)->getMock();
        $orderMock->method('getState')->willReturn(Order::STATE_CLOSED);
        $orderMock->method('hasInvoices')->willReturn(true);
        $orderMock->method('canCreditmemo')->willReturn(false);

        $this->invoiceServiceMock->expects($this->never())->method('prepareInvoice');
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getAllItems')->willReturn([]);
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getBaseGrandTotal')->willReturn(133.50);

        $this->creditmemoManagementMock->expects($this->never())->method('refund');
        $this->creditEmailSenderMock->expects($this->never())->method('send');

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(40.0);
        $instance->order = $orderMock;

        $this->assertTrue($instance->createCreditmemo());
    }

    /**
     * A push for a refund that failed at Buckaroo creates no credit memo.
     */
    public function testReceiveRefundPushForFailedRefundCreatesNoCreditmemo()
    {
        $postData = $this->createMock(PushRequestInterfaceStub::class);
        $postData->method('getTransactions')->willReturn('trans123');
        $postData->method('getStatusCode')->willReturn('490');
        $postData->method('getStatusMessage')->willReturn('Failed');

        $this->configRefundMock->method('getAllowPush')->willReturn(true);
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');
        $this->creditmemoManagementMock->expects($this->never())->method('refund');

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund failed with status 490: Failed');

        $this->getInstance()->receiveRefundPush($postData, true, $this->buildOrder());
    }

    /**
     * Without a valid signature the push is still accepted for an order that can be credited.
     */
    public function testUnsignedPushIsAcceptedForCreditableOrder()
    {
        $this->expectPushToReachConfiguration(false, $this->buildOrder(['getPayment' => null]), false);
    }

    /**
     * Captured order without invoice (deferred invoicing) that is not fully refunded yet.
     */
    public function testUnsignedPushIsAcceptedForCapturedOrderWithAmountLeft()
    {
        $order = $this->buildOrder([
            'canCreditmemo'        => false,
            'getPayment'           => $this->paymentPaid(50.0),
            'getBaseTotalRefunded' => 49.99,
        ]);

        $this->expectPushToReachConfiguration(false, $order, false);
    }

    public function testUnsignedPushIsRejectedForFullyRefundedCapture()
    {
        $order = $this->buildOrder([
            'canCreditmemo'        => false,
            'getPayment'           => $this->paymentPaid(50.0),
            'getBaseTotalRefunded' => 50.0,
        ]);

        $this->expectPushToReachConfiguration(false, $order, true);
    }

    public function testUnsignedPushIsRejectedForUncapturedOrder()
    {
        $order = $this->buildOrder([
            'canCreditmemo'        => false,
            'getPayment'           => $this->paymentPaid(0.0),
            'getBaseTotalRefunded' => -1.0,
        ]);

        $this->expectPushToReachConfiguration(false, $order, true);
    }

    /**
     * A push without any transaction key is not looked up among the credit memos.
     */
    public function testReceiveRefundPushWithMissingTransactionKeyIsNotLookedUp()
    {
        $postData = $this->createMock(PushRequestInterface::class);
        $postData->method('getTransactions')->willReturn(null);

        $collection = $this->getFakeMock(CreditmemoCollection::class)->getMock();
        $collection->expects($this->never())->method('getItemsByColumnValue');
        $order = $this->buildOrder(['getCreditmemosCollection' => $collection]);

        $this->configRefundMock->method('getAllowPush')->willReturn(false);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund is disabled');

        $this->getInstance()->receiveRefundPush($postData, true, $order);
    }

    /**
     * Riverty refund on an order that was captured but never invoiced: an offline invoice is
     * created first, items are credited by ordered quantity and the grand total is the pushed amount.
     */
    public function testRivertyRefundOfUninvoicedOrderInvoicesFirstAndSetsGrandTotal()
    {
        $payment = $this->paymentPaid(133.50);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('buckaroo_refund_transaction_key', 'trans123');
        $item = $this->getFakeMock(OrderItem::class)->getMock();
        $item->method('getId')->willReturn(7);
        $item->method('getQtyInvoiced')->willReturn(0.0);
        $item->method('getQtyOrdered')->willReturn(3.0);
        $item->method('getQtyRefunded')->willReturn(1.0);
        $order = $this->buildOrder([
            'hasInvoices' => false,
            'getPayment'  => $payment,
            'getAllItems' => [$item],
        ]);
        $magicCalls = [];
        $order->method('__call')->willReturnCallback(function ($name, $args) use (&$magicCalls) {
            $magicCalls[] = [$name, $args];
            return null;
        });

        $invoice = $this->getFakeMock(Invoice::class)
            ->onlyMethods(['getTotalQty', 'register', 'getOrder', 'getIncrementId', 'getGrandTotal'])
            ->getMock();
        $invoice->method('getTotalQty')->willReturn(2.0);
        $invoice->method('getOrder')->willReturn($order);
        $invoice->expects($this->once())->method('register');
        $this->invoiceServiceMock->expects($this->once())
            ->method('prepareInvoice')
            ->with($this->identicalTo($order))
            ->willReturn($invoice);
        $this->transactionMock->expects($this->exactly(2))->method('addObject')->willReturnSelf();
        $this->transactionMock->expects($this->once())->method('save');

        $creditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemo->method('isValidGrandTotal')->willReturn(true);
        $creditmemo->method('getAllItems')->willReturn([]);
        $creditmemo->expects($this->once())->method('setBaseGrandTotal')->with(133.50);
        $creditmemo->expects($this->once())->method('setGrandTotal')->with(133.50);
        $capturedData = null;
        $this->creditmemoFactoryMock->expects($this->once())
            ->method('createByOrder')
            ->willReturnCallback(function ($order, $data) use (&$capturedData, $creditmemo) {
                $capturedData = $data;
                return $creditmemo;
            });
        $this->expectRefundAndEmail($creditmemo);
        $this->configRefundMock->method('getAllowPush')->willReturn(true);

        $result = $this->getInstance()->receiveRefundPush(
            $this->rivertyPush('afterpay', 'C041', false, 133.50),
            true,
            $order
        );

        $this->assertTrue($result);
        $this->assertSame('offline', $invoice->getData('requested_capture_case'));
        $this->assertContains(['setIsInProcess', [true]], $magicCalls);
        $this->assertSame([7 => ['qty' => 2]], $capturedData['items']);
        $this->assertSame([7 => 2], $capturedData['qtys']);
    }

    /**
     * Riverty refund of an invoiced order whose capture came from Magento: grand total is set.
     */
    public function testRivertyRefundAfterMagentoCaptureSetsGrandTotal()
    {
        $creditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemo->method('isValidGrandTotal')->willReturn(true);
        $creditmemo->expects($this->once())->method('setBaseGrandTotal')->with(40.0);
        $creditmemo->expects($this->once())->method('setGrandTotal')->with(40.0);
        $this->invoiceServiceMock->expects($this->never())->method('prepareInvoice');

        $this->createCreditmemoFor($this->rivertyPush('afterpay', 'C041', true, 40.0), $creditmemo);
    }

    /**
     * @return array
     */
    public static function nonAdjustedRefundProvider(): array
    {
        return [
            'riverty, invoiced, not captured from Magento' => ['afterpay', 'C041', false],
            'other method with the Riverty refund type'    => ['ideal', 'C041', true],
            'riverty with another transaction type'        => ['afterpay', 'C040', true],
            'no method'                                    => ['', 'C041', true],
        ];
    }

    /**
     * @param string $method
     * @param string $type
     * @param bool $capturedFromMagento
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonAdjustedRefundProvider')]
    public function testGrandTotalIsLeftAloneForOtherRefunds(string $method, string $type, bool $capturedFromMagento)
    {
        $creditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemo->method('isValidGrandTotal')->willReturn(true);
        $creditmemo->expects($this->never())->method('setBaseGrandTotal');
        $creditmemo->expects($this->never())->method('setGrandTotal');

        $this->createCreditmemoFor($this->rivertyPush($method, $type, $capturedFromMagento, 40.0), $creditmemo);
    }

    public function testCreditmemoWithInvalidTotalIsNotRefunded()
    {
        $creditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemo->method('isValidGrandTotal')->willReturn(false);
        $creditmemo->method('getAllItems')->willReturn([]);
        $this->creditmemoFactoryMock->method('createByOrder')->willReturn($creditmemo);
        $this->creditmemoManagementMock->expects($this->never())->method('refund');
        $this->creditEmailSenderMock->expects($this->never())->method('send');

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(40.0);
        $instance->order = $this->buildOrder();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The credit memo\'s total must be positive.');

        $instance->createCreditmemo();
    }

    public function testCreateCreditmemoFailsWhenFactoryGivesNoCreditmemo()
    {
        $this->creditmemoFactoryMock->method('createByOrder')->willReturn(false);
        $this->creditmemoManagementMock->expects($this->never())->method('refund');

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(40.0);
        $instance->order = $this->buildOrder();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Failed to create the creditmemo');

        $instance->createCreditmemo();
    }

    public function testInitCreditmemoReturnsFalseWhenItCannotBeBuilt()
    {
        $this->creditmemoFactoryMock->method('createByOrder')
            ->willThrowException(new LocalizedException(__('Cannot create creditmemo')));

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder();

        $this->assertFalse($instance->initCreditmemo([]));
    }

    public function testInitCreditmemoSkipsCancelledOrder()
    {
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');
        $this->invoiceServiceMock->expects($this->never())->method('prepareInvoice');

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder(['getState' => Order::STATE_CANCELED, 'hasInvoices' => false]);

        $this->assertFalse($instance->initCreditmemo([]));
    }

    /**
     * Credit memo items follow the store's "return to stock" setting.
     */
    public function testInitCreditmemoReturnsItemsToStockPerConfiguration()
    {
        $item = $this->getFakeMock(CreditmemoItem::class)->onlyMethods([])->getMock();
        $creditmemo = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemo->method('getAllItems')->willReturn([$item]);
        $this->creditmemoFactoryMock->method('createByOrder')->willReturn($creditmemo);
        $this->invoiceServiceMock->expects($this->never())->method('prepareInvoice');
        $this->scopeConfigMock->method('isSetFlag')
            ->with('cataloginventory/item_options/auto_return', 'store', 1)
            ->willReturn(true);

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder();

        $this->assertSame($creditmemo, $instance->initCreditmemo(['do_offline' => '0']));
        $this->assertTrue($item->getData('back_to_stock'));
    }

    /**
     * An uninvoiced item of an order that was not captured is not credited.
     */
    public function testUninvoicedItemOfUncapturedOrderIsNotCredited()
    {
        $item = $this->getFakeMock(OrderItem::class)->getMock();
        $item->method('getId')->willReturn(7);
        $item->method('getQtyInvoiced')->willReturn(0.0);
        $item->method('getQtyOrdered')->willReturn(3.0);

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder(['getAllItems' => [$item], 'getPayment' => $this->paymentPaid(0.0)]);
        $instance->creditAmount = 133.50;

        $this->assertSame([7 => ['qty' => 0]], $instance->getCreditmemoDataItems());
    }

    /**
     * An order without invoice whose invoice would hold no products gets no credit memo.
     */
    public function testInitCreditmemoStopsWhenTheInvoiceHasNoProducts()
    {
        $invoice = $this->getFakeMock(Invoice::class)->onlyMethods(['getTotalQty', 'register'])->getMock();
        $invoice->method('getTotalQty')->willReturn(0.0);
        $invoice->expects($this->never())->method('register');
        $this->invoiceServiceMock->method('prepareInvoice')->willReturn($invoice);
        $this->transactionMock->expects($this->never())->method('save');
        $this->creditmemoFactoryMock->expects($this->never())->method('createByOrder');

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder(['hasInvoices' => false]);

        $this->assertFalse($instance->initCreditmemo([]));
    }

    public function testInvoicedItemsAreCreditedForWhatIsNotRefundedYet()
    {
        $item = $this->getFakeMock(OrderItem::class)->getMock();
        $item->method('getId')->willReturn(7);
        $item->method('getQtyInvoiced')->willReturn(4.0);
        $item->method('getQtyRefunded')->willReturn(1.0);

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder(['getAllItems' => [$item]]);
        $instance->creditAmount = 133.50;

        $this->assertSame([7 => ['qty' => 3]], $instance->getCreditmemoDataItems());
    }

    public function testShippingToRefundIncludesTaxWhenConfigured()
    {
        $this->scopeConfigMock->method('getValue')
            ->with(Push::TAX_CALCULATION_SHIPPING_INCLUDES_TAX, 'store', 1)
            ->willReturn('1');

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder();

        // (10.00 + 2.10) - (4.00 + 0.84)
        $this->assertEqualsWithDelta(7.26, $instance->caluclateShippingCostToRefund(), 0.0001);
    }

    public function testShippingToRefundExcludesTaxByDefault()
    {
        $this->scopeConfigMock->method('getValue')->willReturn('0');

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder();

        $this->assertSame(6.0, $instance->caluclateShippingCostToRefund());
    }

    public function testCreditAdjustmentsAddUpAllPreviousCreditmemos()
    {
        $first = $this->getFakeMock(Creditmemo::class)->getMock();
        $first->method('getBaseAdjustmentPositive')->willReturn(5.0);
        $first->method('getBaseAdjustmentNegative')->willReturn(1.5);
        $second = $this->getFakeMock(Creditmemo::class)->getMock();
        $second->method('getBaseAdjustmentPositive')->willReturn(3.0);
        $second->method('getBaseAdjustmentNegative')->willReturn(0.0);
        $collection = $this->getFakeMock(CreditmemoCollection::class)->onlyMethods(['getIterator'])->getMock();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$first, $second]));

        $instance = $this->getInstance();
        $instance->order = $this->buildOrder(['getCreditmemosCollection' => $collection]);

        $this->assertSame(6.5, $instance->getTotalCreditAdjustments());
    }

    /**
     * Grand total 133.50, already refunded 40.00; still open: shipping 6, subtotal 70,
     * adjustments -3, tax 15, discount -7 = 81, so 12.50 is left as remainder.
     */
    public function testRemainderIsWhatTheOpenTotalsDoNotCover()
    {
        $this->scopeConfigMock->method('getValue')->willReturn(null);

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(93.50);
        $instance->order = $this->buildOrder([
            'getBaseTotalRefunded'       => 40.0,
            'getBaseSubtotalRefunded'    => 30.0,
            'getBaseAdjustmentNegative'  => 2.0,
            'getBaseAdjustmentPositive'  => 5.0,
            'getBaseTaxRefunded'         => 6.0,
            'getBaseDiscountAmount'      => -10.0,
            'getBaseDiscountRefunded'    => -3.0,
        ]);

        $this->assertSame(12.5, $instance->calculateRemainder());
    }

    public function testRemainderIsZeroWhenTheGrandTotalIsRefunded()
    {
        $this->scopeConfigMock->method('getValue')->willReturn(null);

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(133.50);
        $instance->order = $this->buildOrder(['getBaseSubtotal' => 50.0]);

        $this->assertSame(0, $instance->calculateRemainder());
    }

    /**
     * A single cent is still refunded as remainder.
     */
    public function testRemainderOfOneCentIsKept()
    {
        $this->scopeConfigMock->method('getValue')->willReturn(null);

        $instance = $this->getInstance();
        $instance->postData = $this->getPostDataMock(0.0);
        $instance->order = $this->zeroOrder(0.01);

        $this->assertSame(0.01, $instance->calculateRemainder());
    }

    /**
     * Push that passes or fails the signature/refundability check; when it passes it stops at
     * the disabled refund configuration.
     *
     * @param bool $signatureValidation
     * @param Order $order
     * @param bool $rejected
     */
    private function expectPushToReachConfiguration(bool $signatureValidation, $order, bool $rejected): void
    {
        $postData = $this->createMock(PushRequestInterface::class);
        $postData->method('getTransactions')->willReturn('trans123');
        $this->configRefundMock->method('getAllowPush')->willReturn(false);

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage(
            $rejected ? 'Buckaroo refund push validation failed' : 'Buckaroo refund is disabled'
        );

        $this->getInstance()->receiveRefundPush($postData, $signatureValidation, $order);
    }

    /**
     * Run createCreditmemo() for a partial refund of an invoiced order with the given push.
     *
     * @param PushRequestInterfaceStub $postData
     * @param Creditmemo $creditmemo
     */
    private function createCreditmemoFor($postData, $creditmemo): void
    {
        $creditmemo->method('getAllItems')->willReturn([]);
        $this->creditmemoFactoryMock->method('createByOrder')->willReturn($creditmemo);
        $this->creditmemoManagementMock->expects($this->once())
            ->method('refund')
            ->with($this->identicalTo($creditmemo), $this->identicalTo(false), $this->identicalTo(false));

        $instance = $this->getInstance();
        $instance->postData = $postData;
        $instance->order = $this->buildOrder();

        $this->assertTrue($instance->createCreditmemo());
    }

    /**
     * @param string $method
     * @param string $type
     * @param bool $capturedFromMagento
     * @param float $amount
     * @return MockObject|PushRequestInterfaceStub
     */
    private function rivertyPush(string $method, string $type, bool $capturedFromMagento, float $amount)
    {
        $postData = $this->createMock(PushRequestInterfaceStub::class);
        $postData->method('getTransactions')->willReturn('trans123');
        $postData->method('getCurrency')->willReturn('EUR');
        $postData->method('getTransactionMethod')->willReturn($method);
        $postData->method('getTransactionType')->willReturn($type);
        $postData->method('getAmountCredit')->willReturn($amount);
        $postData->method('getStatusCode')->willReturn('190');
        $postData->method('hasAdditionalInformation')->willReturnMap([
            ['service_action_from_magento', 'capture', $capturedFromMagento],
        ]);

        return $postData;
    }

    /**
     * @param float $amountPaid
     * @return MockObject|Payment
     */
    private function paymentPaid(float $amountPaid)
    {
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $payment->method('getBaseAmountPaid')->willReturn($amountPaid);
        $payment->method('getAdditionalInformation')->willReturn(null);

        return $payment;
    }

    /**
     * Invoiced EUR order of 133.50 (subtotal 100, shipping 10 + 2.10 tax, tax 21) with shipping
     * 4.00 + 0.84 tax already refunded; values can be overridden per method.
     *
     * @param array $overrides
     * @return MockObject|OrderStub
     */
    private function buildOrder(array $overrides = [])
    {
        $collection = $this->getFakeMock(CreditmemoCollection::class)
            ->onlyMethods(['getItemsByColumnValue', 'getIterator'])
            ->getMock();
        $collection->method('getItemsByColumnValue')->willReturn([]);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $values = $overrides + [
            'getId'                     => 1,
            'getStoreId'                => 1,
            'getIncrementId'            => '000000042',
            'getState'                  => Order::STATE_PROCESSING,
            'canCreditmemo'             => true,
            'hasInvoices'               => true,
            'getCreditmemosCollection'  => $collection,
            'getPayment'                => $this->paymentPaid(133.50),
            'getBaseCurrencyCode'       => 'EUR',
            'getBaseToOrderRate'        => 1.0,
            'getBaseGrandTotal'         => 133.50,
            'getBaseTotalRefunded'      => 0.0,
            'getBaseSubtotal'           => 100.0,
            'getBaseSubtotalRefunded'   => 0.0,
            'getBaseShippingAmount'     => 10.0,
            'getBaseShippingTaxAmount'  => 2.10,
            'getBaseShippingRefunded'   => 4.0,
            'getBaseShippingTaxRefunded' => 0.84,
            'getBaseTaxAmount'          => 21.0,
            'getBaseTaxRefunded'        => 0.0,
            'getBaseDiscountAmount'     => 0.0,
            'getBaseDiscountRefunded'   => 0.0,
            'getBaseAdjustmentPositive' => 0.0,
            'getBaseAdjustmentNegative' => 0.0,
            'getAllItems'               => [],
        ];

        $order = $this->getFakeMock(OrderStub::class)->getMock();
        foreach ($values as $method => $value) {
            $order->method($method)->willReturn($value);
        }

        return $order;
    }

    /**
     * Order whose totals are all zero except the grand total.
     *
     * @param float $grandTotal
     * @return MockObject|OrderStub
     */
    private function zeroOrder(float $grandTotal)
    {
        $order = $this->getFakeMock(OrderStub::class)->getMock();
        $order->method('getStoreId')->willReturn(1);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');
        $order->method('getBaseGrandTotal')->willReturn($grandTotal);

        return $order;
    }
}
