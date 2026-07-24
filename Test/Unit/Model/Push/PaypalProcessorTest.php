<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PaypalProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\PaypalProcessor';

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
    private $paypalConfigMock;

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
        $this->paypalConfigMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal')->getMock();

        // The processor constructor does not receive a CurrencyFactory and falls back to
        // ObjectManager::getInstance(); provide one for the duration of the test.
        $appObjectManagerMock = $this->getFakeMock('Magento\Framework\ObjectManagerInterface')->getMock();
        $appObjectManagerMock->method('get')->willReturn(
            $this->getFakeMock('Magento\Directory\Model\CurrencyFactory')->getMock()
        );
        \Magento\Framework\App\ObjectManager::setInstance($appObjectManagerMock);
    }

    public function tearDown(): void
    {
        $property = new \ReflectionProperty(\Magento\Framework\App\ObjectManager::class, '_instance');
        $property->setValue(null, null);

        parent::tearDown();
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
            'paypalConfig'          => $this->paypalConfigMock,
        ]);
    }

    private function prepareGetNewStatus($instance, string $statusKey, string $paymentMethodCode)
    {
        $pushRequestMock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $pushRequestMock->method('getStatusCode')->willReturn('190');

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethod')->willReturn($paymentMethodCode);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $this->pushTransactionTypeMock->method('getStatusKey')->willReturn($statusKey);

        $this->orderStatusFactoryMock->expects($this->once())
            ->method('get')
            ->with('190', $orderMock)
            ->willReturn('base_success_status');

        $this->setProperty('pushRequest', $pushRequestMock, $instance);
        $this->setProperty('order', $orderMock, $instance);
    }

    public function testGetNewStatusUsesSellersProtectionIneligibleStatusForSuccessfulPaypalPush(): void
    {
        $instance = $this->getInstance();

        $this->prepareGetNewStatus(
            $instance,
            'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS',
            'buckaroo_magento2_paypal'
        );

        $this->paypalConfigMock->method('getSellersProtection')->willReturn(true);
        $this->paypalConfigMock->method('getSellersProtectionIneligible')
            ->willReturn('sellers_protection_ineligible');

        $this->assertSame('sellers_protection_ineligible', $this->invoke('getNewStatus', $instance));
    }

    public static function sellersProtectionNotAppliedProvider(): array
    {
        return [
            'sellers protection disabled' => [
                'sellersProtection'  => false,
                'ineligibleStatus'   => 'sellers_protection_ineligible',
            ],
            'no ineligible status configured' => [
                'sellersProtection'  => true,
                'ineligibleStatus'   => '',
            ],
        ];
    }

    #[DataProvider('sellersProtectionNotAppliedProvider')]
    public function testGetNewStatusKeepsFactoryStatusWhenSellersProtectionDoesNotApply(
        bool $sellersProtection,
        string $ineligibleStatus
    ): void {
        $instance = $this->getInstance();

        $this->prepareGetNewStatus(
            $instance,
            'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS',
            'buckaroo_magento2_paypal'
        );

        $this->paypalConfigMock->method('getSellersProtection')->willReturn($sellersProtection);
        $this->paypalConfigMock->method('getSellersProtectionIneligible')->willReturn($ineligibleStatus);

        $this->assertSame('base_success_status', $this->invoke('getNewStatus', $instance));
    }

    public function testGetNewStatusKeepsFactoryStatusForNonSuccessPush(): void
    {
        $instance = $this->getInstance();

        $this->prepareGetNewStatus(
            $instance,
            'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING',
            'buckaroo_magento2_paypal'
        );

        $this->paypalConfigMock->expects($this->never())->method('getSellersProtectionIneligible');

        $this->assertSame('base_success_status', $this->invoke('getNewStatus', $instance));
    }

    public function testGetNewStatusKeepsFactoryStatusForOtherPaymentMethod(): void
    {
        $instance = $this->getInstance();

        $this->prepareGetNewStatus(
            $instance,
            'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS',
            'buckaroo_magento2_ideal'
        );

        $this->paypalConfigMock->expects($this->never())->method('getSellersProtectionIneligible');

        $this->assertSame('base_success_status', $this->invoke('getNewStatus', $instance));
    }
}
