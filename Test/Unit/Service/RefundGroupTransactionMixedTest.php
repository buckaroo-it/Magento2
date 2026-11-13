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

namespace Buckaroo\Magento2\Test\Unit\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

/**
 * Admin credit memo for an order paid with a giftcard, a voucher and iDEAL: the form posts one
 * row per giftcard and voucher, each is refunded on its own transaction, and only what is left
 * goes to iDEAL.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundGroupTransactionMixedTest extends TestCase
{
    public function testGiftcardAndVoucherAreRefundedAndOnlyTheRestGoesToIdeal(): void
    {
        $items = [
            'GC-KEY' => $this->groupTransaction('GC-KEY', 'vvvgiftcard', 10.00),
            'VC-KEY' => $this->groupTransaction('VC-KEY', 'buckaroovoucher', 10.00),
            'ID-KEY' => $this->groupTransaction('ID-KEY', 'ideal', 14.00),
        ];

        $sent = [];
        $service = $this->createService($items, [
            'creditmemo' => [
                'buckaroo_already_paid' => [
                    'GC-KEY|vvvgiftcard|10.00'     => '10',
                    'VC-KEY|buckaroovoucher|10.00' => '10',
                ],
            ],
        ], $sent);

        $buildSubject = ['payment' => $this->createPaymentDataObject(), 'amount' => 34.00];
        $leftForPrimaryMethod = $service->refundGroupTransactions($buildSubject);

        $this->assertSame(
            [
                ['vvvgiftcard', 10.0, 'GC-KEY'],
                ['buckaroovoucher', 10.0, 'VC-KEY'],
                ['ideal', 14.0, 'ID-KEY'],
            ],
            $sent
        );
        $this->assertSame(0, $leftForPrimaryMethod);
        $this->assertSame(10.0, $items['VC-KEY']->getRefundedAmount());
    }

    /**
     * @param array<string, GroupTransaction> $items
     * @param array                           $params
     * @param array                           $sent   receives [payment_method, amountCredit, originalTransactionKey]
     */
    private function createService(array $items, array $params, array &$sent): RefundGroupTransactionService
    {
        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getAnyGroupTransactionItems')->willReturn(array_values($items));
        $groupTransaction->method('getGroupTransactionByTrxId')->willReturnCallback(
            function (string $key) use ($items) {
                return $items[$key];
            }
        );
        $groupTransaction->method('getGroupTransactionAmount')->willReturn(34.0);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn($params);

        $requestDataBuilder = $this->createMock(BuilderInterface::class);
        $requestDataBuilder->method('build')->willReturn([]);

        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturnCallback(
            function (array $body) use (&$sent) {
                $sent[] = [$body['payment_method'], (float)$body['amountCredit'], $body['originalTransactionKey']];
                return $this->createMock(TransferInterface::class);
            }
        );

        $response = $this->createMock(TransactionResponse::class);
        $response->method('isSuccess')->willReturn(true);
        $response->method('getStatusCode')->willReturn(190);
        $client = $this->createMock(ClientInterface::class);
        $client->method('placeRequest')->willReturn(['object' => $response]);

        $giftcardCollection = $this->createMock(GiftcardCollection::class);
        $giftcardCollection->method('getItemByColumnValue')->willReturnMap([
            ['servicecode', 'vvvgiftcard', new DataObject(['servicecode' => 'vvvgiftcard', 'label' => 'VVV'])],
        ]);

        return new RefundGroupTransactionService(
            $groupTransaction,
            $this->createMock(BuckarooLoggerInterface::class),
            $request,
            $requestDataBuilder,
            $transferFactory,
            $client,
            $giftcardCollection,
            $this->createMock(GroupTransactionResource::class)
        );
    }

    private function createPaymentDataObject(): PaymentDataObjectInterface
    {
        $order = $this->createMock(Order::class);
        $order->method('getBaseGrandTotal')->willReturn(34.0);
        $order->method('getIncrementId')->willReturn('000000042');

        $orderAdapter = $this->getMockBuilder(OrderAdapterInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['getOrder'])
            ->getMockForAbstractClass();
        $orderAdapter->method('getOrder')->willReturn($order);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $payment->method('getAdditionalInformation')->willReturn(null);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($orderAdapter);
        $paymentDO->method('getPayment')->willReturn($payment);

        return $paymentDO;
    }

    private function groupTransaction(string $transactionId, string $serviceCode, float $amount): GroupTransaction
    {
        $groupTransaction = $this->getMockBuilder(GroupTransaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $groupTransaction->setData([
            'entity_id'       => crc32($transactionId),
            'transaction_id'  => $transactionId,
            'servicecode'     => $serviceCode,
            'amount'          => $amount,
            'refunded_amount' => null,
        ]);

        return $groupTransaction;
    }
}
