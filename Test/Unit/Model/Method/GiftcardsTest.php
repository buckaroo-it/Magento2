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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use TIG\Buckaroo\Model\Method\Giftcards;
use TIG\Buckaroo\Test\BaseTest;

class GiftcardsTest extends BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Method\Giftcards
     */
    protected $object;

    /**
     * @var \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory|\Mockery\MockInterface
     */
    protected $transactionBuilderFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\Mockery\MockInterface
     */
    protected $scopeConfig;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Factory|\Mockery\MockInterface
     */
    protected $configProviderMethodFactory;

    /**
     * @var \Magento\Framework\ObjectManagerInterface|\Mockery\MockInterface
     */
    protected $objectManager;

    public function setUp()
    {
        parent::setUp();

        $this->objectManager = \Mockery::mock(ObjectManagerInterface::class);
        $this->transactionBuilderFactory = \Mockery::mock(TransactionBuilderFactory::class);
        $this->scopeConfig = \Mockery::mock(ScopeConfigInterface::class);
        $this->configProviderMethodFactory = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Method\Factory::class);

        $this->object = $this->objectManagerHelper->getObject(
            Giftcards::class,
            [
            'objectManager' => $this->objectManager,
            'scopeConfig' => $this->scopeConfig,
            'transactionBuilderFactory' => $this->transactionBuilderFactory,
            'configProviderMethodFactory' => $this->configProviderMethodFactory,
            ]
        );
    }

    public function testCanCaptureShouldReturnTrue()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn('fake_order');

        $this->assertTrue($this->object->canCapture());
    }

    public function testCanCaptureShouldReturnFalse()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn('order');

        $this->assertFalse($this->object->canCapture());
    }

    public function testIsAvailableNotAllowedGiftcards()
    {
        $giftcardsConfig = \Mockery::mock(GiftcardsConfig::class);
        $giftcardsConfig->shouldReceive('getAllowedGiftcards')->andReturn(null);

        $this->configProviderMethodFactory->shouldReceive('get')->with('giftcards')->andReturn($giftcardsConfig);

        $quote = \Mockery::mock(\Magento\Quote\Api\Data\CartInterface::class);

        $this->assertFalse($this->object->isAvailable($quote));
    }

    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'allowed_giftcards' => 'bookgiftcard,webshopgiftcard',
            'order' => 'order'
        ];

        $payment = \Mockery::mock(
            InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );

        $payment->shouldReceive('getOrder')->andReturn($fixture['order']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $order->shouldReceive('setCustomVars')->andReturnUsing(
            function ($customVars) use ($fixture, $order) {
                $this->assertEquals(
                    $fixture['allowed_giftcards'] . ',ideal',
                    $customVars['ServicesSelectableByClient']
                );
                $this->assertEquals('RedirectToHTML', $customVars['ContinueOnIncomplete']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);
        $this->scopeConfig->shouldReceive('getValue')
            ->with(GiftcardsConfig::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS, ScopeInterface::SCOPE_STORE)
            ->andReturn($fixture['allowed_giftcards']);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getOrderTransactionBuilder($payment));
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $fixture = [
            'name' => 'giftcards',
            'action' => 'Authorize',
            'order' => 'order'
        ];

        $payment = \Mockery::mock(
            InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );

        $payment->shouldReceive('getOrder')->andReturn($fixture['order']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['name'], $services['Name']);
                $this->assertEquals($fixture['action'], $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getAuthorizeTransactionBuilder($payment));
    }

    public function testGetCaptureTransactionBuilder()
    {
	$this->markTestSkipped('invoice counter not supported');

        $fixture = [
            'name' => 'giftcards',
            'action' => 'Capture',
            'transaction_key' => 'key!'
        ];

        $invoiceMock = \Mockery::mock(\Magento\Sales\Model\Order\Invoice::class);
        $invoiceMock->shouldReceive('getBaseGrandTotal')->andReturn(25);

        $paymentOrder = \Mockery::mock(\Magento\Sales\Model\Order::class);
        $paymentOrder->shouldReceive('getBaseGrandTotal')->andReturn(25);
        $paymentOrder->shouldReceive('hasInvoices')->andReturn(true);
        $paymentOrder->shouldReceive('getInvoiceCollection')->andReturn([$invoiceMock]);

        $payment = \Mockery::mock(
            InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );

        $payment->shouldReceive('getOrder')->andReturn($paymentOrder);
        $payment->shouldReceive('getAdditionalInformation')
            ->with(Giftcards::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->andReturn($fixture['transaction_key']);

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('setOrder')->with($paymentOrder)->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();
        $order->shouldReceive('setChannel')->with('CallCenter')->andReturnSelf();
        $order->shouldReceive('setOriginalTransactionKey')->with($fixture['transaction_key'])->andReturnSelf();

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['name'], $services['Name']);
                $this->assertEquals($fixture['action'], $services['Action']);

                return $order;
            }
        );

        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(InfoInterface::class)->makePartial();

        $this->object->setData('info_instance', $infoInterface);
        $this->assertEquals($order, $this->object->getCaptureTransactionBuilder($payment));
    }

    public function testGetRefundTransactionBuilder()
    {
        $this->assertFalse($this->object->getRefundTransactionBuilder(null));
    }

    public function testGetVoidTransactionBuilder()
    {
        $this->assertTrue($this->object->getVoidTransactionBuilder(null));
    }
}
