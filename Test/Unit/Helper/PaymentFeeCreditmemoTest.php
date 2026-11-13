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

namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\TestCase;

/**
 * The credit memo form posts one "already paid" amount per row built here, and the refund service
 * refunds exactly the posted rows. A partial payment without a row is never refunded, and its
 * amount is sent to the other payment method instead.
 */
class PaymentFeeCreditmemoTest extends TestCase
{
    public function testGiftcardAndVoucherBothGetARefundRowNextToTheOtherMethod(): void
    {
        $rows = $this->getCreditmemoRows([
            $this->groupTransaction('GC-KEY', 'vvvgiftcard', 10.00),
            $this->groupTransaction('VC-KEY', 'buckaroovoucher', 10.00),
            $this->groupTransaction('ID-KEY', 'ideal', 14.00),
        ]);

        $this->assertSame(
            [
                'GC-KEY|vvvgiftcard|10' => ['Paid with VVV Cadeaukaart', -10.0],
                'VC-KEY|buckaroovoucher|10' => ['Paid with Voucher', -10.0],
            ],
            $rows
        );
    }

    public function testAFullyRefundedVoucherOffersNothingMore(): void
    {
        $rows = $this->getCreditmemoRows([
            $this->groupTransaction('VC-KEY', 'buckaroovoucher', 10.00, 10.00),
        ]);

        $this->assertSame([], $rows);
    }

    /**
     * @param GroupTransaction[] $items
     *
     * @return array<string, array{0: string, 1: float}>
     */
    private function getCreditmemoRows(array $items): array
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn([]);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getAlreadyPaid')->willReturn(34.0);
        $groupTransaction->method('getGroupTransactionItems')->willReturn($items);

        $giftcardCollection = $this->createMock(GiftcardCollection::class);
        $giftcardCollection->method('getItemByColumnValue')->willReturnMap([
            ['servicecode', 'vvvgiftcard', new DataObject([
                'servicecode'           => 'vvvgiftcard',
                'label'                 => 'VVV Cadeaukaart',
                'is_partial_refundable' => 0,
            ])],
        ]);

        $helper = new PaymentFee(
            $context,
            $this->createMock(AccountConfigProvider::class),
            $this->createMock(BuckarooFeeConfigProvider::class),
            $this->createMock(MethodConfigProviderFactory::class),
            $groupTransaction,
            $giftcardCollection
        );

        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('000000042');
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);

        $totals = [];
        $helper->addAlreadyPayedTotals($creditmemo, $totals);

        $rows = [];
        foreach ($totals as $total) {
            if (($total['block_name'] ?? null) === 'buckaroo_already_paid') {
                $rows[$total['transaction_id']] = [(string)$total['label'], (float)$total['value']];
            }
        }

        return $rows;
    }

    private function groupTransaction(
        string $transactionId,
        string $serviceCode,
        float $amount,
        ?float $refunded = null
    ): GroupTransaction {
        $groupTransaction = $this->getMockBuilder(GroupTransaction::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $groupTransaction->setData([
            'transaction_id'  => $transactionId,
            'servicecode'     => $serviceCode,
            'amount'          => $amount,
            'refunded_amount' => $refunded,
        ]);

        return $groupTransaction;
    }
}
