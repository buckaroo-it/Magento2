<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\Push\PushProcessorInterface;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use PHPUnit\Framework\Attributes\DataProvider;

class PushProcessorsFactoryTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\PushProcessorsFactory';

    private const PROCESSORS = [
        'default'           => 'DefaultProcessorClass',
        'ideal'             => 'IdealProcessorClass',
        'klarnakp'          => 'KlarnaKpProcessorClass',
        'payperemail'       => 'PayPerEmailProcessorClass',
        'group_transaction' => 'GroupTransactionProcessorClass',
        'credit_managment'  => 'CreditManagementProcessorClass',
        'refund'            => 'RefundProcessorClass',
        'cancel_authorize'  => 'CancelAuthorizeProcessorClass',
    ];

    private $orderRequestServiceMock;
    private $objectManagerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRequestServiceMock = $this->getFakeMock('Buckaroo\Magento2\Service\Push\OrderRequestService')
            ->getMock();
        $this->objectManagerMock = $this->getFakeMock('Magento\Framework\ObjectManagerInterface')->getMock();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'orderRequestService' => $this->orderRequestServiceMock,
            'objectManager'       => $this->objectManagerMock,
            'pushProcessors'      => self::PROCESSORS,
        ]);
    }

    /**
     * Build a PushTransactionType mock describing an incoming push.
     */
    private function createPushTransactionType(
        string $paymentMethod = 'unknownmethod',
        bool $isFromPayPerEmail = false,
        bool $isGroupTransaction = false,
        string $pushType = PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
        ?string $serviceAction = null
    ) {
        $mock = $this->getFakeMock(PushTransactionType::class)->getMock();
        $mock->method('getPaymentMethod')->willReturn($paymentMethod);
        $mock->method('isFromPayPerEmail')->willReturn($isFromPayPerEmail);
        $mock->method('isGroupTransaction')->willReturn($isGroupTransaction);
        $mock->method('getPushType')->willReturn($pushType);
        $mock->method('getServiceAction')->willReturn($serviceAction);

        return $mock;
    }

    public function testGetThrowsLogicExceptionWhenNoProcessorsConfigured(): void
    {
        $instance = $this->getInstance(['pushProcessors' => []]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Push processors is not set.');

        $instance->get($this->createPushTransactionType());
    }

    public static function processorResolutionProvider(): array
    {
        return [
            'payment method resolves its own processor, case-insensitive' => [
                'paymentMethod'      => 'iDEAL',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => null,
                'expectedClass'      => 'IdealProcessorClass',
            ],
            'unknown payment method falls back to default processor' => [
                'paymentMethod'      => 'somebrandnewmethod',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => null,
                'expectedClass'      => 'DefaultProcessorClass',
            ],
            'payperemail origin overrides the payment method processor' => [
                'paymentMethod'      => 'ideal',
                'isFromPayPerEmail'  => true,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => 'pay',
                'expectedClass'      => 'PayPerEmailProcessorClass',
            ],
            'payperemail refund goes to the refund processor instead' => [
                'paymentMethod'      => 'ideal',
                'isFromPayPerEmail'  => true,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => 'refund',
                'expectedClass'      => 'RefundProcessorClass',
            ],
            'group transaction push wins over payment method' => [
                'paymentMethod'      => 'ideal',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => true,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => null,
                'expectedClass'      => 'GroupTransactionProcessorClass',
            ],
            'group transaction wins over invoice push type' => [
                'paymentMethod'      => 'ideal',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => true,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_INVOICE,
                'serviceAction'      => null,
                'expectedClass'      => 'GroupTransactionProcessorClass',
            ],
            'invoice push resolves credit management processor' => [
                'paymentMethod'      => 'ideal',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_INVOICE,
                'serviceAction'      => null,
                'expectedClass'      => 'CreditManagementProcessorClass',
            ],
            'refund service action resolves refund processor' => [
                'paymentMethod'      => 'klarnakp',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => 'refund',
                'expectedClass'      => 'RefundProcessorClass',
            ],
            'cancel_authorize service action resolves cancel authorize processor' => [
                'paymentMethod'      => 'klarnakp',
                'isFromPayPerEmail'  => false,
                'isGroupTransaction' => false,
                'pushType'           => PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION,
                'serviceAction'      => 'cancel_authorize',
                'expectedClass'      => 'CancelAuthorizeProcessorClass',
            ],
        ];
    }

    #[DataProvider('processorResolutionProvider')]
    public function testGetResolvesProcessorClass(
        string $paymentMethod,
        bool $isFromPayPerEmail,
        bool $isGroupTransaction,
        string $pushType,
        ?string $serviceAction,
        string $expectedClass
    ): void {
        $instance = $this->getInstance();

        $pushTransactionType = $this->createPushTransactionType(
            $paymentMethod,
            $isFromPayPerEmail,
            $isGroupTransaction,
            $pushType,
            $serviceAction
        );

        $processorMock = $this->getFakeMock(PushProcessorInterface::class)->getMock();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with($expectedClass)
            ->willReturn($processorMock);

        $this->assertSame($processorMock, $instance->get($pushTransactionType));
    }

    public function testGetThrowsBuckarooExceptionForIncompleteInvoicePush(): void
    {
        $instance = $this->getInstance();

        $pushTransactionType = $this->createPushTransactionType(
            'ideal',
            false,
            false,
            PushTransactionType::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE
        );

        $this->objectManagerMock->expects($this->never())->method('get');

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Skipped handling this invoice push because it is too soon.');

        $instance->get($pushTransactionType);
    }

    public function testGetThrowsBuckarooExceptionWhenResolvedProcessorClassIsEmpty(): void
    {
        $instance = $this->getInstance(['pushProcessors' => ['default' => '']]);

        $pushTransactionType = $this->createPushTransactionType('somebrandnewmethod');

        $this->objectManagerMock->expects($this->never())->method('get');

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Unknown Push Processor type');

        $instance->get($pushTransactionType);
    }

    public function testGetCachesTheResolvedProcessorAcrossCalls(): void
    {
        $instance = $this->getInstance();

        $processorMock = $this->getFakeMock(PushProcessorInterface::class)->getMock();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with('IdealProcessorClass')
            ->willReturn($processorMock);

        $firstCallType = $this->createPushTransactionType('ideal');
        // A second call with a completely different push type must not re-resolve.
        $secondCallType = $this->createPushTransactionType('klarnakp');

        $this->assertSame($processorMock, $instance->get($firstCallType));
        $this->assertSame($processorMock, $instance->get($secondCallType));
    }
}
