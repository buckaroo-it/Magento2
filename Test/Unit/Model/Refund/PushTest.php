<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\Refund;

class PushTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Refund\Push
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $debugger;

    /**
     * @var \Mockery\MockInterface
     */
    protected $order;

    /**
     * @var \Mockery\MockInterface
     */
    protected $creditmemoFactory;

    /**
     * @var \Mockery\MockInterface
     */
    protected $creditmemoManagement;

    /**
     * @var \Mockery\MockInterface
     */
    protected $configRefund;

    /**
     * Setup the base mock objects.
     */
    public function setUp()
    {
        parent::setUp();

        $this->order = \Mockery::mock(\Magento\Sales\Model\Order::class)->makePartial();
        $this->debugger = \Mockery::mock(\TIG\Buckaroo\Debug\Debugger::class)->makePartial();
        $this->debugger->shouldReceive('addToMessage', 'log')->andReturnSelf();
        $this->creditmemoManagement = \Mockery::mock(\Magento\Sales\Api\CreditmemoManagementInterface::class);
        $this->creditmemoFactory = \Mockery::mock(\Magento\Sales\Model\Order\CreditmemoFactory::class);
        $this->configRefund = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Refund::class);

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Refund\Push::class,
            [
                'debugger' => $this->debugger,
                'creditmemoManagement' => $this->creditmemoManagement,
                'creditmemoFactory' => $this->creditmemoFactory,
                'configRefund' => $this->configRefund
            ]
        );

        $this->object->order = $this->order;
    }

    /**
     * Test the happy path of the receiveRefundMethod.
     */
    public function testReceiveRefundPush()
    {
        $id = rand(1, 1000);
        $creditmemoMock = $this->getFakeMock(\Magento\Sales\Model\Order\Creditmemo::class)
            ->setMethods(['getAllItems', 'isValidGrandTotal', 'setTransactionId'])
            ->getMock();
        $creditmemoMock->expects($this->any())->method('getAllItems')->willReturn([]);
        $creditmemoMock->expects($this->any())->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->expects($this->once())->method('setTransactionId')->with($id);

        $this->creditmemoFactory->shouldReceive('createByOrder')->once()->andReturn($creditmemoMock);
        $this->creditmemoFactory->shouldReceive('getItems')->andReturn([]);
        $this->creditmemoFactory->shouldReceive('getItemsByColumnValue')->with('transaction_id', $id)->andReturn([]);

        $this->configRefund->shouldReceive('getAllowPush')->andReturn(true);

        $this->order->shouldReceive('getId')->once()->andReturn($id);
        $this->order->shouldReceive('getCreditmemosCollection')->andReturn($this->creditmemoFactory);
        $this->order->shouldReceive('getItemsCollection')->andReturn($this->creditmemoFactory);

        $this->debugger->shouldReceive('addToMessage', 'log')->andReturnSelf();

        $this->creditmemoManagement->shouldReceive('refund')->once();

        $postData = [
            'brq_currency' => false,
            'brq_amount_credit' => 0,
            'brq_transactions' => $id,
        ];
        $signatureValidation = true;
        $result = $this->object->receiveRefundPush($postData, $signatureValidation, $this->order);

        $this->assertTrue($result);
    }

    /**
     * Test the path of the receiveRefundMethod where the signature is invalid.
     */
    public function testReceiveRefundPushInvalidSignature()
    {
        $this->order->shouldReceive('canCreditmemo')->twice()->andReturn(false);
        $this->configRefund->shouldReceive('getAllowPush')->andReturn(true);

        try {
            $this->object->receiveRefundPush([], false, $this->order);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\TIG\Buckaroo\Exception::class, $e);
            $this->assertEquals('Buckaroo refund push validation failed', $e->getMessage());
        }
    }

    /**
     * Test the path with an invalid grand total
     */
    public function testCreateCreditMemoInvalidGrandTotal()
    {
        $creditmemoItem = \Mockery::mock(\Magento\Sales\Model\Order\Creditmemo\Item::class);
        $creditmemoItem->shouldReceive('setBackToStock', 'isDeleted');
        $creditmemoItem->shouldReceive('getId', 'getQtyInvoiced', 'getQtyRefunded')->andReturn(1);

        $this->creditmemoFactory->shouldReceive('getItems')->andReturn([]);
        $this->creditmemoFactory->shouldReceive('getAllItems')->andReturn([$creditmemoItem]);
        $this->creditmemoFactory->shouldReceive('isValidGrandTotal')->andReturn(false);
        $this->creditmemoFactory->shouldReceive('createByOrder')->once()->andReturnSelf();

        $this->order->shouldReceive('getCreditmemosCollection')->andReturn($this->creditmemoFactory);
        $this->order->shouldReceive('getItemsCollection')->andReturn($this->creditmemoFactory);

        $this->object->createCreditmemo();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }

    /**
     * Test the path with an invalid grand total
     */
    public function testCreateCreditMemoUnableToCreate()
    {
        $this->creditmemoFactory->shouldReceive('getItems')->andReturn([]);
        $this->creditmemoFactory->shouldReceive('getAllItems')->andReturn([]);
        $this->creditmemoFactory->shouldReceive('isValidGrandTotal')->andReturn(false);
        $exception = new \TIG\Buckaroo\Exception(__('Error for test'));
        $this->creditmemoFactory->shouldReceive('createByOrder')->once()->andThrow($exception);

        $this->order->shouldReceive('getCreditmemosCollection')->andReturn($this->creditmemoFactory);
        $this->order->shouldReceive('getItemsCollection')->andReturn($this->creditmemoFactory);

        $this->assertFalse($this->object->createCreditmemo());
    }

    /**
     * Unit test for the getCreditmemoData method.
     */
    public function testGetCreditmemoData()
    {
        $this->order->shouldReceive('getBaseGrandTotal')->andReturn(999);
        $this->order->shouldReceive('getBaseTotalRefunded')->andReturn('0');
        $this->order->shouldReceive('getBaseToOrderRate')->andReturn('1');
        $this->order->shouldReceive('getAllItems')->andReturn(array());

        $this->object->postData = [
            'brq_currency' => 'EUR',
            'brq_amount_credit' => '100'
        ];
        $result = $this->object->getCreditmemoData();

        $this->assertEquals(0, $result['shipping_amount']);
        $this->assertEquals(0, $result['adjustment_negative']);
        $this->assertEquals(array(), $result['items']);
        $this->assertEquals(0, $result['qtys']);
        $this->assertEquals('100', $result['adjustment_positive']);
    }

    /**
     * Unit test for the getTotalCreditAdjustments method.
     */
    public function testGetTotalCreditAdjustments()
    {
        $creditmemo = \Mockery::mock(\Magento\Sales\Model\Order\Creditmemo::class);
        $creditmemo->shouldReceive('getBaseAdjustmentPositive')->andReturn(10);
        $creditmemo->shouldReceive('getBaseAdjustmentNegative')->andReturn(5);

        $this->order->shouldReceive('getCreditmemosCollection')->andReturn([$creditmemo]);

        $this->assertEquals(5, $this->object->getTotalCreditAdjustments());
    }

    /**
     * Unit test for the getAdjustmentRefundData method.
     */
    public function testGetAdjustmentRefundData()
    {
        $this->object->postData = [
            'brq_currency' => 'EUR',
            'brq_amount_credit' => '100',
        ];

        $this->order->shouldReceive('getBaseToOrderRate')->once()->andReturn(1);
        $this->order->shouldReceive('getBaseTotalRefunded')->once()->andReturn(null);
        $this->order->shouldReceive('getBaseBuckarooFeeInvoiced')->andReturn(10);
        $this->order->shouldReceive('getBuckarooFeeBaseTaxAmountInvoiced')->andReturn(5);

        $this->assertEquals(85, $this->object->getAdjustmentRefundData());
    }

    /**
     * Unit test for the getCreditmemoDataItems method.
     */
    public function testGetCreditmemoDataItems()
    {
        $orderItem = \Mockery::mock(\Magento\Sales\Model\Order\Item::class);
        $orderItem->shouldReceive('getId')->andReturn(1);
        $orderItem->shouldReceive('getQtyInvoiced')->andReturn(10);
        $orderItem->shouldReceive('getQtyRefunded')->andReturn(3);

        $this->order->shouldReceive('getAllItems')->andReturn([$orderItem]);

        $result = $this->object->getCreditmemoDataItems();

        $this->assertEquals(7, $result[1]['qty']);
    }

    /**
     * Unit test for the setCreditQtys method.
     */
    public function testSetCreditQtys()
    {
        $items = [
            15 => ['qty' => 30],
            16 => ['qty' => 32],
        ];

        $result = $this->object->setCreditQtys($items);

        $this->assertEquals(30, $result[15]);
        $this->assertEquals(32, $result[16]);
    }
}
