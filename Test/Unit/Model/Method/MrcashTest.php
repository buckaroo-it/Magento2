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

class MrcashTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Method\Mrcash
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
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->objectManager = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->transactionBuilderFactory = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory::class);

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Method\Mrcash::class,
            [
            'objectManager' => $this->objectManager,
            'transactionBuilderFactory' => $this->transactionBuilderFactory,
            ]
        );
    }

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'issuer' => 'nlbace',
            'order' => 'orderrr!',
        ];

        $payment = \Mockery::mock(
            \Magento\Payment\Model\InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );

        $payment->shouldReceive('getOrder')->andReturn($fixture['order']);
        $payment->shouldReceive('getAdditionalInformation')->with('issuer')->andReturn($fixture['issuer']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals('bancontactmrcash', $services['Name']);
                $this->assertEquals('Pay', $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(\Magento\Payment\Model\InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getOrderTransactionBuilder($payment));
    }

    /**
     * Test the getCaptureTransactionBuilder method.
     */
    public function testGetCaptureTransactionBuilder()
    {
        $this->assertFalse($this->object->getCaptureTransactionBuilder(''));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        $this->assertFalse($this->object->getAuthorizeTransactionBuilder(''));
    }

    /**
     * Test the getRefundTransactionBuilder method.
     */
    public function testGetRefundTransactionBuilder()
    {
        $payment = \Mockery::mock(
            \Magento\Payment\Model\InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );

        $payment->shouldReceive('getOrder')->andReturn('orderr');
        $payment->shouldReceive('getAdditionalInformation')->with(
            \TIG\Buckaroo\Model\Method\Mrcash::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        )->andReturn('getAdditionalInformation');

        $this->transactionBuilderFactory->shouldReceive('get')->with('refund')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOrder')->with('orderr')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setServices')->andReturnUsing(
            function ($services) {
                $services['Name'] = 'mrcash';
                $services['Action'] = 'Refund';

                return $this->transactionBuilderFactory;
            }
        );
        $this->transactionBuilderFactory->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setChannel')->with('CallCenter')->andReturnSelf();

        $this->assertEquals($this->transactionBuilderFactory, $this->object->getRefundTransactionBuilder($payment));
    }

    /**
     * Test the getVoidTransactionBuild method.
     */
    public function testGetVoidTransactionBuilder()
    {
        $this->assertTrue($this->object->getVoidTransactionBuilder(''));
    }
}
