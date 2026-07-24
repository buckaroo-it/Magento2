<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Push\IdealPushProcessor;

class IdealPushProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\IdealPushProcessor';

    private $orderRequestServiceMock;
    private $pushTransactionTypeMock;
    private $loggerMock;
    private $helperMock;
    private $transactionMock;
    private $groupTransactionMock;
    private $buckarooStatusCodeMock;
    private $orderStatusFactoryMock;
    private $configAccountMock;
    private $giftCardRefundServiceMock;
    private $uncancelServiceMock;
    private $resourceConnectionMock;
    private $giftcardCollectionMock;
    private $currencyFactoryMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRequestServiceMock = $this->getFakeMock('Buckaroo\Magento2\Service\Push\OrderRequestService')->getMock();
        $this->pushTransactionTypeMock = $this->getFakeMock('Buckaroo\Magento2\Model\Push\PushTransactionType')->getMock();
        $this->loggerMock = $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock();
        $this->helperMock = $this->getFakeMock('Buckaroo\Magento2\Helper\Data')->getMock();
        $this->transactionMock = $this->getFakeMock('Magento\Sales\Api\Data\TransactionInterface')->getMock();
        $this->groupTransactionMock = $this->getFakeMock('Buckaroo\Magento2\Helper\PaymentGroupTransaction')->getMock();
        $this->buckarooStatusCodeMock = $this->getFakeMock('Buckaroo\Magento2\Model\BuckarooStatusCode')->getMock();
        $this->orderStatusFactoryMock = $this->getFakeMock('Buckaroo\Magento2\Model\OrderStatusFactory')->getMock();
        $this->configAccountMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Account')->getMock();
        $this->giftCardRefundServiceMock = $this->getFakeMock('Buckaroo\Magento2\Model\Service\GiftCardRefundService')->getMock();
        $this->uncancelServiceMock = $this->getFakeMock('Buckaroo\Magento2\Service\Order\Uncancel')->getMock();
        $this->resourceConnectionMock = $this->getFakeMock('Magento\Framework\App\ResourceConnection')->getMock();
        $this->giftcardCollectionMock = $this->getFakeMock('Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection')->getMock();
        $this->currencyFactoryMock = $this->getFakeMock('Magento\Directory\Model\CurrencyFactory')->getMock();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'orderRequestService'   => $this->orderRequestServiceMock,
            'pushTransactionType'   => $this->pushTransactionTypeMock,
            'logger'                => $this->loggerMock,
            'helper'                => $this->helperMock,
            'transaction'           => $this->transactionMock,
            'groupTransaction'      => $this->groupTransactionMock,
            'buckarooStatusCode'    => $this->buckarooStatusCodeMock,
            'orderStatusFactory'    => $this->orderStatusFactoryMock,
            'configAccount'         => $this->configAccountMock,
            'giftCardRefundService' => $this->giftCardRefundServiceMock,
            'uncancelService'       => $this->uncancelServiceMock,
            'resourceConnection'    => $this->resourceConnectionMock,
            'giftcardCollection'    => $this->giftcardCollectionMock,
            'currencyFactory'       => $this->currencyFactoryMock,
        ]);
    }

    public function testGetSpecificPaymentDetailsIsEmptyForIdeal(): void
    {
        // iDEAL overrides the DefaultProcessor hook to add no method-specific
        // payment information (no issuer/consumer details) to the order payment.
        $this->assertSame([], $this->invoke('getSpecificPaymentDetails', $this->getInstance()));
    }

    public function testIdealPushUsesDedicatedLockPrefixAndPayTransactionType(): void
    {
        $this->assertSame('C021', IdealPushProcessor::BUCK_PUSH_IDEAL_PAY);

        $lockPrefix = new \ReflectionClassConstant(IdealPushProcessor::class, 'LOCK_PREFIX');
        $this->assertSame('bk_push_ideal_', $lockPrefix->getValue());
        $this->assertSame(
            IdealPushProcessor::class,
            $lockPrefix->getDeclaringClass()->getName(),
            'IdealPushProcessor must declare its own lock prefix so iDEAL pushes are locked separately'
        );
    }
}
