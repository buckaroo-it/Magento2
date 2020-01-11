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
namespace TIG\Buckaroo\Test\Unit\Controller\Redirect;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Mockery as m;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Helper\Data;
use TIG\Buckaroo\Controller\Redirect\Process;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Cart;

class ProcessTest extends BaseTest
{
    /**
     * @var Process
     */
    protected $controller;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var m\MockInterface
     */
    protected $request;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var m\MockInterface
     */
    protected $order;

    /**
     * @var m\MockInterface
     */
    protected $cart;

    /**
     * @var m\MockInterface
     */
    protected $messageManager;

    /**
     * @var m\MockInterface
     */
    protected $redirect;

    /**
     * @var m\MockInterface
     */
    protected $configProviderFactory;

    /**
     * @var m\MockInterface
     */
    protected $orderStatusFactory;

    /**
     * Setup the base mocks
     */
    public function setUp()
    {
        parent::setUp();

        $this->request = m::mock(RequestInterface::class);
        $this->helper = $this->objectManagerHelper->getObject(Data::class);
        $this->cart = m::mock(Cart::class);
        $this->order = m::mock(Order::class);
        $this->messageManager = m::mock(ManagerInterface::class);
        $this->redirect = m::mock(RedirectInterface::class);

        $this->configProviderFactory = m::mock(\TIG\Buckaroo\Model\ConfigProvider\Factory::class)->makePartial();
        $this->configProviderFactory->shouldReceive('get')->with('account')->andReturnSelf();

        $this->orderStatusFactory = m::mock(\TIG\Buckaroo\Model\OrderStatusFactory::class)->makePartial();

        $this->context = $this->objectManagerHelper->getObject(
            Context::class,
            [
            'request' => $this->request,
            'redirect' => $this->redirect,
            'messageManager' => $this->messageManager,
            ]
        );

        $this->controller = $this->objectManagerHelper->getObject(
            Process::class,
            [
            'context' => $this->context,
            'helper' => $this->helper,
            'order' => $this->order,
            'cart' => $this->cart,
            'configProviderFactory' => $this->configProviderFactory,
            'orderStatusFactory' => $this->orderStatusFactory,
            ]
        );
    }

    /**
     * Test the path with no parameters set.
     *
     * @throws Exception
     * @throws \Exception
     */
    public function testExecute()
    {
        $this->request->shouldReceive('getParams')->andReturn([]);

        $this->redirect->shouldReceive('redirect')->once()->with(\Mockery::any(), '/', []);

        $this->controller->execute();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }

    /**
     * Test the path when we are unable to create a quote.
     */
    public function testExecuteUnableToCreateQuote()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null
        ];

        $this->request->shouldReceive('getParams')->andReturn($params);
        $failureStatus = 'failure';

        $this->configProviderFactory->shouldReceive('getFailureRedirect')->andReturn('failure_url');
        $this->configProviderFactory->shouldReceive('getCancelOnFailed')->andReturn(true);
        $this->configProviderFactory->shouldReceive('getOrderStatusFailed')->andReturn($failureStatus);

        $this->cart->shouldReceive('setQuote')->once()->andReturnSelf();
        $this->cart->shouldReceive('save')->once()->andReturn(false);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData'])
            ->getMock();
        $payment->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $this->order->shouldReceive('loadByIncrementId')->with(null)->andReturnSelf();
        $this->order->shouldReceive('getId')->andReturnNull();
        $this->order->shouldReceive('getState')->once()->andReturn('!canceled');
        $this->order->shouldReceive('canCancel')->once()->andReturn(true);
        $this->order->shouldReceive('cancel')->once()->andReturnSelf();
        $this->order->shouldReceive('setStatus')->once()->with($failureStatus)->andReturnSelf();
        $this->order->shouldReceive('getStore')->andReturnSelf();
        $this->order->shouldReceive('save')->once()->andReturnSelf();
        $this->order->shouldReceive('getPayment')->once()->andReturn($payment);

        $this->orderStatusFactory
            ->shouldReceive('get')
            ->andReturn($failureStatus);

        $this->messageManager->shouldReceive('addErrorMessage');

        $this->redirect->shouldReceive('redirect')->once()->with(\Mockery::any(), 'failure_url', []);

        $this->controller->execute();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }

    /**
     * Test what happens when we are unable to cancel the order.
     */
    public function testExecuteUnableToCancelOrder()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => null
        ];

        $this->request->shouldReceive('getParams')->andReturn($params);

        $this->configProviderFactory->shouldReceive('getFailureRedirect')->andReturn('failure_url');
        $this->configProviderFactory->shouldReceive('getCancelOnFailed')->andReturn(true);

        $this->cart->shouldReceive('setQuote')->once()->andReturnSelf();
        $this->cart->shouldReceive('save')->once()->andReturn(true);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData'])
            ->getMock();
        $payment->expects($this->atLeastOnce())->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $this->order->makePartial();
        $this->order->shouldReceive('loadByIncrementId')->with(null)->andReturnSelf();
        $this->order->shouldReceive('getId')->andReturnNull();
        $this->order->shouldReceive('canCancel')->once()->andReturn(false);
        $this->order->shouldReceive('getStore')->andReturnSelf();
        $this->order->shouldReceive('getPayment')->andReturn($payment);

        $this->messageManager->shouldReceive('addErrorMessage');

        $this->redirect->shouldReceive('redirect')->once()->with(\Mockery::any(), 'failure_url', []);

        $this->controller->execute();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }

    /**
     * Test a success status update.
     *
     * @throws Exception
     * @throws \Exception
     */
    public function testExecuteSuccessStatus()
    {
        $params = [
            'brq_ordernumber' => null,
            'brq_invoicenumber' => null,
            'brq_statuscode' => $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS'),
        ];

        $this->request->shouldReceive('getParams')->andReturn($params);
        $successStatus = 'success';

        $this->configProviderFactory->shouldReceive('getOrderStatusPending')->andReturn('tig_buckaroo_new');
        $this->configProviderFactory->shouldReceive('getSuccessRedirect')->andReturn('success_url');
        $this->configProviderFactory->shouldReceive('getOrderConfirmationEmail')->andReturn('0');

        $this->orderStatusFactory
            ->shouldReceive('get')
            ->andReturn($successStatus);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'canProcessPostData'])
            ->getMock();
        $payment->expects($this->atLeastOnce())->method('getMethodInstance')->willReturnSelf();
        $payment->expects($this->once())->method('canProcessPostData')->with($payment, $params)->willReturn(true);

        $this->order->shouldReceive('loadByIncrementId')->with(null)->andReturnSelf();
        $this->order->shouldReceive('getId')->andReturn(true);
        $this->order->shouldReceive('canInvoice')->once()->andReturn(true);
        $this->order->shouldReceive('getQuoteId')->andReturn(1);
        $this->order->shouldReceive('setStatus')->once()->andReturnSelf();
        $this->order->shouldReceive('save')->once()->andReturnSelf();
        $this->order->shouldReceive('getEmailSent')->once()->andReturn(1);
        $this->order->shouldReceive('getStore')->andReturnSelf();
        $this->order->shouldReceive('getPayment')->andReturn($payment);

        $this->messageManager->shouldReceive('addSuccessMessage')->once();

        $this->redirect->shouldReceive('redirect')->once()->with(\Mockery::any(), 'success_url', []);

        $this->controller->execute();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }
}
