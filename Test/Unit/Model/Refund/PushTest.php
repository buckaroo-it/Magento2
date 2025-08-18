<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Refund;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
use Buckaroo\Magento2\Model\Refund\Push;
use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\Exception\LocalizedException;

abstract class PushRequestStub implements PushRequestInterface
{
    public function hasAdditionalInformation($key, $value)
    {
        return $this->getAdditionalInformation($key) === $value;
    }

    public function getAmountCredit()
    {
        return 0.0;
    }

    public function validate($store = null): bool
    {
        return true;
    }

    public function getAmountDebit()
    {
        return null;
    }

    public function getAmount()
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    public function getCustomerName(): ?string
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getInvoiceNumber(): ?string
    {
        return null;
    }

    public function getMutationType(): ?string
    {
        return null;
    }

    public function getOrderNumber(): ?string
    {
        return null;
    }

    public function getPayment(): ?string
    {
        return null;
    }

    public function getStatusCode(): ?string
    {
        return null;
    }

    public function getStatusCodeDetail(): ?string
    {
        return null;
    }

    public function getStatusMessage(): ?string
    {
        return null;
    }

    public function isTest(): bool
    {
        return false;
    }

    public function getTransactionMethod(): ?string
    {
        return null;
    }

    public function getTransactionType(): ?string
    {
        return null;
    }

    public function getTransactions(): ?string
    {
        return null;
    }

    public function setTransactions($transactions)
    {
    }

    public function setAmount($amount): void
    {
    }

    public function getAdditionalInformation(string $propertyName): ?string
    {
        return null;
    }
}

class PushTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Push::class;

    private MockObject|CreditmemoFactory $creditmemoFactoryMock;

    private MockObject|CreditmemoManagementInterface $creditmemoManagementMock;

    private MockObject|CreditmemoSender $creditEmailSenderMock;

    private MockObject|Refund $configRefundMock;

    private MockObject|Data $helperMock;

    private MockObject|BuckarooLoggerInterface $loggerMock;

    private MockObject|ScopeConfigInterface $scopeConfigMock;

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
        ] + $args);
    }

    public function testReceiveRefundPushSuccess()
    {
        $postDataMock = $this->getMockForAbstractClass(
            PushRequestStub::class,
            [],
            '',
            true,
            true,
            true,
            ['getTransactions', 'getAdditionalInformation', 'getTransactionMethod', 'getTransactionType', 'getAmountCredit', 'hasAdditionalInformation', 'getCurrency']
        );

        $postDataMock->method('getTransactions')->willReturn('trans123');
        $postDataMock->method('getAdditionalInformation')->willReturn(null);
        $postDataMock->method('hasAdditionalInformation')->with('service_action_from_magento', 'capture')->willReturn(false);
        $postDataMock->method('getCurrency')->willReturn('EUR');
        $postDataMock->method('getTransactionMethod')->willReturn('afterpay');
        $postDataMock->method('getTransactionType')->willReturn('C041');
        $postDataMock->method('getAmountCredit')->willReturn(100.0);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('canCreditmemo')->willReturn(true);
        $creditmemoCollectionMock = $this->getFakeMock(\Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection::class)
            ->onlyMethods(['getItemsByColumnValue'])
            ->getMock();
        $creditmemoCollectionMock->method('getItemsByColumnValue')->willReturn([]);
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $orderMock->method('getBaseGrandTotal')->willReturn(100.0);
        $orderMock->method('getBaseTotalRefunded')->willReturn(0.0);
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getBaseToOrderRate')->willReturn(1.0);
        $orderMock->method('getAllItems')->willReturn([]);

        $paymentMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn([]);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(true);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->getMock();
        $creditmemoMock->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->method('setTransactionId')->willReturnSelf();
        $creditmemoMock->method('getAllItems')->willReturn([]);

        $this->creditmemoFactoryMock->method('createByOrder')->willReturn($creditmemoMock);

        $this->creditmemoManagementMock->method('refund')->willReturn($creditmemoMock);

        $this->creditEmailSenderMock->method('send')->willReturn(true);

        $instance = $this->getInstance();
        $result = $instance->receiveRefundPush($postDataMock, true, $orderMock);

        $this->assertTrue($result);
    }

    public function testReceiveRefundPushDisabled()
    {
        $postDataMock = $this->getMockForAbstractClass(PushRequestInterface::class);
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $this->configRefundMock->method('getAllowPush')->willReturn(false);

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund is disabled');

        $instance->receiveRefundPush($postDataMock, true, $orderMock);
    }

    public function testReceiveRefundPushExistingCreditmemo()
    {
        $postDataMock = $this->getMockForAbstractClass(
            PushRequestInterface::class,
            [],
            '',
            true,
            true,
            true,
            ['getTransactions']
        );
        $postDataMock->method('getTransactions')->willReturn('trans123');

        $creditmemoCollectionMock = $this->getFakeMock(\Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection::class)->getMock();
        $creditmemoCollectionMock->method('getItemsByColumnValue')->willReturn([new \stdClass()]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);

        $this->configRefundMock->method('getAllowPush')->willReturn(true);

        $instance = $this->getInstance();

        $result = $instance->receiveRefundPush($postDataMock, true, $orderMock);

        $this->assertFalse($result);
    }

    public function testReceiveRefundPushInvalidSignature()
    {
        $postDataMock = $this->getMockForAbstractClass(PushRequestInterface::class);
        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('canCreditmemo')->willReturn(false);

        $this->configRefundMock->method('getAllowPush')->willReturn(true);

        $instance = $this->getInstance();

        $this->expectException(BuckarooException::class);
        $this->expectExceptionMessage('Buckaroo refund push validation failed');

        $instance->receiveRefundPush($postDataMock, false, $orderMock);
    }

    public function testCreateCreditmemoFailure()
    {
        // Test the actual functionality - when createCreditmemo returns false
        $postDataMock = $this->getMockForAbstractClass(
            PushRequestStub::class,
            [],
            '',
            true,
            true,
            true,
            ['getAmountCredit', 'getCurrency']
        );
        $postDataMock->method('getAmountCredit')->willReturn(100.0);
        $postDataMock->method('getCurrency')->willReturn('EUR');

        $paymentMock = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
        $paymentMock->method('getAdditionalInformation')->willReturn([]);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getBaseGrandTotal')->willReturn(100.0);
        $orderMock->method('getBaseTotalRefunded')->willReturn(0.0);
        $orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $orderMock->method('getBaseToOrderRate')->willReturn(1.0);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getAllItems')->willReturn([]);
        $orderMock->method('getCreditmemosCollection')->willReturn([]);

        // Mock the factory to throw an exception, which will make initCreditmemo return false
        $this->creditmemoFactoryMock->method('createByOrder')
            ->willThrowException(new LocalizedException(__('Cannot create creditmemo')));

        $this->helperMock->method('areEqualAmounts')->willReturn(true);

        // Create a partial mock with proper constructor arguments
        $constructorArgs = [
            $this->creditmemoFactoryMock,
            $this->creditmemoManagementMock,
            $this->creditEmailSenderMock,
            $this->configRefundMock,
            $this->helperMock,
            $this->loggerMock,
            $this->scopeConfigMock
        ];

        $instance = $this->getMockBuilder($this->instanceClass)
            ->setConstructorArgs($constructorArgs)
            ->getMock();

        $instance->postData = $postDataMock;
        $instance->order = $orderMock;

        // The method should return false when creditmemo creation fails
        $result = $instance->createCreditmemo();
        $this->assertFalse($result);
    }
}
