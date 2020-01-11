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
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use TIG\Buckaroo\Model\Method\Creditcard;

class CreditcardTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Creditcard::class;

    /**
     * @var Creditcard
     */
    protected $object;

    /**
     * @var \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory|\Mockery\MockInterface
     */
    protected $transactionBuilderFactory;

    /**
     * @var \Magento\Framework\ObjectManagerInterface|\Mockery\MockInterface
     */
    protected $objectManager;

    /**
     * @var InfoInterface|\Magento\Sales\Api\Data\OrderPaymentInterface|\Mockery\MockInterface
     */
    protected $paymentInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\Mockery\MockInterface
     */
    protected $scopeConfig;

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $productMetadata = \Mockery::mock(\Magento\Framework\App\ProductMetadata::class)->makePartial();
        $this->objectManager = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->objectManager->shouldReceive('get')
            ->with('Magento\Framework\App\ProductMetadataInterface')
            ->andReturn($productMetadata);

        $this->transactionBuilderFactory = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory::class);
        $this->scopeConfig = \Mockery::mock(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        $this->object = $this->objectManagerHelper->getObject(
            Creditcard::class,
            [
            'scopeConfig' => $this->scopeConfig,
            'objectManager' => $this->objectManager,
            'transactionBuilderFactory' => $this->transactionBuilderFactory,
            ]
        );

        $this->paymentInterface = \Mockery::mock(
            InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );
    }

    /**
     * Test the assignData method.
     */
    public function testAssignData()
    {
        $data = $this->getObject(DataObject::class);
        $data->setBuckarooSkipValidation(0);
        $data->setAdditionalData([
            'buckaroo_skip_validation' => 1,
            'card_type' => 'maestro'
        ]);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['setAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInstanceMock->expects($this->exactly(3))->method('setAdditionalInformation')->withConsecutive(
            ['buckaroo_skip_validation', 0],
            ['buckaroo_skip_validation', 1],
            ['card_type', 'maestro']
        );

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($data);
        $this->assertInstanceOf(Creditcard::class, $result);
    }

    /**
     * Test the canCapture method on the happy path.
     */
    public function testCanCapture()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn('nonorder');

        $this->assertTrue($this->object->canCapture());
    }

    /**
     * Test the canCapture method on the less happy path.
     */
    public function testCanCaptureDisabled()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn('order');

        $this->assertFalse($this->object->canCapture());
    }

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
        ];

        $this->paymentInterface->shouldReceive('getOrder')->andReturn($fixture['order']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')
            ->with('card_type')
            ->andReturn($fixture['card_type']);
        $this->paymentInterface->shouldReceive('setAdditionalInformation')->with('skip_push', 1);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Pay', $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getOrderTransactionBuilder($this->paymentInterface));
    }

    /**
     * Test the getCaptureTransactionBuilder method.
     */
    public function testGetCaptureTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
            'transaction_key' => 'key!',
        ];

        $this->paymentInterface->shouldReceive('getOrder')->andReturn($fixture['order']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')
            ->with('card_type')
            ->andReturn($fixture['card_type']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')->with(
            Creditcard::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        )->andReturn($fixture['transaction_key']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();
        $order->shouldReceive('setChannel')->with('CallCenter')->andReturnSelf();
        $order->shouldReceive('setOriginalTransactionKey')->with($fixture['transaction_key'])->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Capture', $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getCaptureTransactionBuilder($this->paymentInterface));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
            'transaction_key' => 'key!',
        ];

        $this->paymentInterface->shouldReceive('getOrder')->andReturn($fixture['order']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')
            ->with('card_type')
            ->andReturn($fixture['card_type']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Authorize', $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getAuthorizeTransactionBuilder($this->paymentInterface));
    }

    /**
     * Test the getRefundTransactionBuilder method.
     */
    public function testGetRefundTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
        ];

        $this->paymentInterface->shouldReceive('getOrder')->andReturn('orderr');
        $this->paymentInterface->shouldReceive('getAdditionalInformation')
            ->with('card_type')
            ->andReturn($fixture['card_type']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')->with(
            Creditcard::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        )->andReturn('getAdditionalInformation');

        $this->transactionBuilderFactory->shouldReceive('get')->with('refund')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOrder')->with('orderr')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setServices')->andReturnUsing(
            function ($services) {
                $services['Name'] = 'creditcard';
                $services['Action'] = 'Refund';

                return $this->transactionBuilderFactory;
            }
        );
        $this->transactionBuilderFactory->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setChannel')->with('CallCenter')->andReturnSelf();

        $this->assertEquals(
            $this->transactionBuilderFactory,
            $this->object->getRefundTransactionBuilder($this->paymentInterface)
        );
    }

    /**
     * Test the getVoidTransactionBuild method.
     */
    public function testGetVoidTransactionBuilder()
    {
        $this->assertTrue($this->object->getVoidTransactionBuilder(''));
    }
}
