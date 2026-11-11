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

namespace Buckaroo\Magento2\Test\Unit\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * `PaymentGroupTransaction::getGroupTransactionByTrxId()` returns ONE model. Iterating it with
 * foreach walks the model's (non-existent) public properties, so a successful giftcard refund
 * never recorded `refunded_amount`, and a later partial refund offered the same giftcard again.
 */
class RefundGroupTransactionServiceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = RefundGroupTransactionService::class;

    /**
     * @var PaymentGroupTransaction|MockObject
     */
    private $paymentGroupTransaction;

    /**
     * @var GroupTransactionResource|MockObject
     */
    private $groupTransactionResource;

    /**
     * @var RefundGroupTransactionService
     */
    private $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentGroupTransaction = $this->getFakeMock(PaymentGroupTransaction::class)->getMock();
        $this->groupTransactionResource = $this->getFakeMock(GroupTransactionResource::class)->getMock();

        $requestDataBuilder = $this->getFakeMock(BuilderInterface::class)->getMock();
        $requestDataBuilder->method('build')->willReturn([]);

        $transferFactory = $this->getFakeMock(TransferFactoryInterface::class)->getMock();
        $transferFactory->method('create')->willReturn($this->getFakeMock(TransferInterface::class)->getMock());

        $response = $this->getFakeMock(TransactionResponse::class)->getMock();
        $response->method('isSuccess')->willReturn(true);
        $response->method('getStatusCode')->willReturn(190);
        $client = $this->getFakeMock(ClientInterface::class)->getMock();
        $client->method('placeRequest')->willReturn(['object' => $response]);

        $this->service = new RefundGroupTransactionService(
            $this->paymentGroupTransaction,
            $this->getFakeMock(BuckarooLoggerInterface::class)->getMock(),
            $this->getFakeMock(RequestInterface::class)->getMock(),
            $requestDataBuilder,
            $transferFactory,
            $client,
            $this->getFakeMock(GiftcardCollection::class)->getMock(),
            $this->groupTransactionResource
        );
        $this->service->setAmountLeftToRefund(40.05);
    }

    public function testASuccessfulGiftcardRefundIsRecordedOnItsGroupTransaction(): void
    {
        $groupTransaction = $this->groupTransaction(null);

        $this->groupTransactionResource->expects($this->once())->method('save')->with($groupTransaction);

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 10.00);

        $this->assertSame(10.0, $groupTransaction->getRefundedAmount());
        $this->assertEqualsWithDelta(30.05, $this->service->getAmountLeftToRefund(), 0.001);
    }

    public function testAPartialGiftcardRefundAddsToWhatWasRefundedBefore(): void
    {
        $groupTransaction = $this->groupTransaction(4.00);

        $this->groupTransactionResource->expects($this->once())->method('save')->with($groupTransaction);

        $this->service->createRefundGroupRequest([], 'B1DA75BE2400480B86D1D37817B81DB1|fashioncheque|10.00', 6.00);

        $this->assertSame(10.0, $groupTransaction->getRefundedAmount());
    }

    /**
     * @param float|null $refundedAmount
     *
     * @return GroupTransaction
     */
    private function groupTransaction(?float $refundedAmount): GroupTransaction
    {
        $groupTransaction = $this->getFakeMock(GroupTransaction::class)->onlyMethods([])->getMock();
        $groupTransaction->setData([
            'entity_id'       => 24,
            'transaction_id'  => 'B1DA75BE2400480B86D1D37817B81DB1',
            'servicecode'     => 'fashioncheque',
            'amount'          => 10.00,
            'refunded_amount' => $refundedAmount,
        ]);

        $this->paymentGroupTransaction->method('getGroupTransactionByTrxId')
            ->with('B1DA75BE2400480B86D1D37817B81DB1')
            ->willReturn($groupTransaction);

        return $groupTransaction;
    }
}
