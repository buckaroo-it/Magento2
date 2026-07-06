<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Magento\Sales\Model\Order;

class DefaultProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\DefaultProcessor';

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
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance([
            'orderRequestService' => $this->orderRequestServiceMock,
            'pushTransactionType' => $this->pushTransactionTypeMock,
            'logger' => $this->loggerMock,
            'helper' => $this->helperMock,
            'transaction' => $this->transactionMock,
            'groupTransaction' => $this->groupTransactionMock,
            'buckarooStatusCode' => $this->buckarooStatusCodeMock,
            'orderStatusFactory' => $this->orderStatusFactoryMock,
            'configAccount' => $this->configAccountMock,
            'giftCardRefundService' => $this->giftCardRefundServiceMock,
            'uncancelService' => $this->uncancelServiceMock,
            'resourceConnection' => $this->resourceConnectionMock,
            'giftcardCollection' => $this->giftcardCollectionMock,
        ] + $args);
    }

    public static function pendingPaymentEmailProvider(): array
    {
        return [
            'sends when not sent, not canceled, global config on' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => true,
            ],
            'sends via method-level order_email when global config off' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => false,
                'methodOrderEmail' => '1',
                'expected' => true,
            ],
            'skips when email already sent' => [
                'emailSent' => true,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => false,
            ],
            'skips when order is canceled' => [
                'emailSent' => false,
                'orderIsCanceled' => true,
                'globalConfigEnabled' => true,
                'methodOrderEmail' => null,
                'expected' => false,
            ],
            'skips when all email configs are off' => [
                'emailSent' => false,
                'orderIsCanceled' => false,
                'globalConfigEnabled' => false,
                'methodOrderEmail' => 0,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider pendingPaymentEmailProvider
     */
    public function testShouldSendPendingPaymentEmail(
        bool $emailSent,
        bool $orderIsCanceled,
        bool $globalConfigEnabled,
        $methodOrderEmail,
        bool $expected
    ): void {
        $instance = $this->getInstance();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getEmailSent')->willReturn($emailSent);

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();
        $methodInstanceMock->method('getConfigData')->willReturn($methodOrderEmail);

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn($globalConfigEnabled);

        $this->setProperty('order', $orderMock, $instance);

        $result = $this->invokeArgs(
            'shouldSendPendingPaymentEmail',
            [$orderIsCanceled, $storeMock, $methodInstanceMock],
            $instance
        );

        $this->assertSame($expected, $result);
    }

    public function testProcessPendingPaymentEmailSendsEmailForWaitingOnConsumerPush(): void
    {
        $instance = $this->getInstance();

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getStore')->willReturn($storeMock);
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('isCanceled')->willReturn(false);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $pushRequestMock = $this->getFakeMock('Buckaroo\Magento2\Api\Data\PushRequestInterface')->getMock();
        $pushRequestMock->method('getStatusCode')->willReturn('792');

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);

        $this->orderRequestServiceMock->expects($this->once())
            ->method('sendOrderEmail')
            ->with($orderMock);

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('processPendingPaymentEmail', [], $instance);
    }

    public function testProcessPendingPaymentEmailSkipsEmailForCanceledOrder(): void
    {
        $instance = $this->getInstance();

        $storeMock = $this->getFakeMock('Magento\Store\Model\Store')->getMock();

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getStore')->willReturn($storeMock);
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('isCanceled')->willReturn(true);
        $orderMock->method('getState')->willReturn(Order::STATE_CANCELED);

        $methodInstanceMock = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();

        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $pushRequestMock = $this->getFakeMock('Buckaroo\Magento2\Api\Data\PushRequestInterface')->getMock();
        $pushRequestMock->method('getStatusCode')->willReturn('792');

        $this->configAccountMock->method('getOrderConfirmationEmail')->willReturn(true);

        $this->orderRequestServiceMock->expects($this->never())
            ->method('sendOrderEmail');

        $this->setProperty('order', $orderMock, $instance);
        $this->setProperty('payment', $paymentMock, $instance);
        $this->setProperty('pushRequest', $pushRequestMock, $instance);

        $this->invokeArgs('processPendingPaymentEmail', [], $instance);
    }
}
