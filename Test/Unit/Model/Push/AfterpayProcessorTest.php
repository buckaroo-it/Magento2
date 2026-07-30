<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class AfterpayProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\AfterpayProcessor';

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
    private $afterpayConfigMock;

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
        $this->afterpayConfigMock = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20')->getMock();

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
            'afterpayConfig'        => $this->afterpayConfigMock,
        ]);
    }

    /**
     * @param array $trueAdditional list of [name, value] pairs for which
     *                              hasAdditionalInformation() returns true
     */
    private function createPushRequest(array $trueAdditional = [])
    {
        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $mock->method('hasAdditionalInformation')->willReturnCallback(
            function ($name, $value) use ($trueAdditional) {
                return in_array([$name, $value], $trueAdditional, true);
            }
        );

        return $mock;
    }

    public function testInvoiceShouldBeSavedDefersInvoiceForMagentoCaptureWithInvoiceAfterShipment(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            ['initiated_by_magento', 1],
            ['service_action_from_magento', 'capture'],
        ]);
        $this->afterpayConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(true);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertFalse($result);
        $this->assertTrue($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testInvoiceShouldBeSavedWhenInvoiceAfterShipmentIsDisabled(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            ['initiated_by_magento', 1],
            ['service_action_from_magento', 'capture'],
        ]);
        $this->afterpayConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(false);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertTrue($result);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testInvoiceShouldBeSavedForPushNotInitiatedByMagento(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest();

        // Short-circuit: the shipment-mode config must not even be consulted.
        $this->afterpayConfigMock->expects($this->never())->method('isInvoiceCreatedAfterShipment');

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertTrue($result);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }

    public function testInvoiceShouldBeSavedForMagentoPushWithDifferentServiceAction(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            ['initiated_by_magento', 1],
            ['service_action_from_magento', 'pay'],
        ]);
        $this->afterpayConfigMock->method('isInvoiceCreatedAfterShipment')->willReturn(true);

        $this->setProperty('pushRequest', $pushRequest, $instance);

        $paymentDetails = [];
        $result = $this->invokeArgs('invoiceShouldBeSaved', [&$paymentDetails], $instance);

        $this->assertTrue($result);
        $this->assertFalse($this->getProperty('dontSaveOrderUponSuccessPush', $instance));
    }
}
