<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
namespace Buckaroo\Magento2\Test\Unit\Model\Method;

use Magento\Developer\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Pricing\Helper\Data as PriceHelperData;
use Magento\Framework\Registry;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Gateway\Http\Transaction;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface;
use Buckaroo\Magento2\Helper\Data as HelperData;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodFactory;
use Buckaroo\Magento2\Model\RefundFieldsFactory;
use Buckaroo\Magento2\Model\ValidatorFactory;

class AbstractMethodTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = AbstractMethodMock::class;

    /**
     * Test what happens if the payment method is disabled.
     */
    public function testIsAvailableDisabled()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class)->getMockForAbstractClass();

        $accountMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountMock->expects($this->once())->method('getActive')->willReturn(0);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountMock);

        $instance = $this->getInstance(['configProviderFactory' => $configProviderMock]);
        $result = $instance->isAvailable($quoteMock);

        $this->assertFalse($result);
    }

    /**
     * Test what happens if the payment method is disabled.
     */
    public function testIsAvailableAdminhtmlDisabled()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class, true);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('adminhtml');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))->method('getValue')
            ->with('payment/buckaroo_magento2_test/available_in_backend', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(0);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'scopeConfig' => $scopeConfigMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertFalse($result);
    }

    /**
     * Test what happens if the allow by ip option is on, but our ip is not in the list.
     */
    public function testIsAvailableInvalidIp()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class, true);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive', 'getLimitByIp'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);
        $accountConfigMock->expects($this->once())->method('getLimitByIp')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('frontend');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);

        $developmentHelperMock = $this->getFakeMock(Data::class)->setMethods(['isDevAllowed'])->getMock();
        $developmentHelperMock->expects($this->once())->method('isDevAllowed')->with(null)->willReturn(false);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'developmentHelper' => $developmentHelperMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertFalse($result);
    }

    /**
     * Test what happens if we exceed the maximum amount. The method should be hidden.
     */
    public function testIsAvailableExceedsMaximum()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class)->setMethods(['getGrandTotal'])->getMockForAbstractClass();
        $quoteMock->expects($this->once())->method('getGrandTotal')->willReturn(25);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('frontend');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(3))->method('getValue')
            ->withConsecutive(
                ['payment/buckaroo_magento2_test/limit_by_ip', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/max_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/min_amount', ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(0, 20, 10);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'scopeConfig' => $scopeConfigMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertFalse($result);
    }

    /**
     * Test what happens if we exceed the minimum amount. The method should be hidden.
     */
    public function testIsAvailableExceedsMinimum()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class)->setMethods(['getGrandTotal'])->getMockForAbstractClass();
        $quoteMock->expects($this->once())->method('getGrandTotal')->willReturn(5);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('frontend');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(3))->method('getValue')
            ->withConsecutive(
                ['payment/buckaroo_magento2_test/limit_by_ip', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/max_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/min_amount', ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(0, 20, 10);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'scopeConfig' => $scopeConfigMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertFalse($result);
    }

    /**
     * Test what happens if we exceed the minimum amount. The method should be hidden.
     */
    public function testIsAvailableNotAllowedCurrency()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class)
            ->setMethods(['getGrandTotal', 'getCurrency', 'getQuoteCurrencyCode'])
            ->getMockForAbstractClass();
        $quoteMock->expects($this->once())->method('getGrandTotal')->willReturn(15);
        $quoteMock->expects($this->once())->method('getCurrency')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getQuoteCurrencyCode')->willReturn('GBP');

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('frontend');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(4))->method('getValue')
            ->withConsecutive(
                ['payment/buckaroo_magento2_test/limit_by_ip', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/max_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/min_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/allowed_currencies', ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(0, 20, 10, 'EUR,USD');

        $instance = $this->getInstance([
            'context' => $contextMock,
            'scopeConfig' => $scopeConfigMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertFalse($result);
    }

    /**
     * Test what happens if the method is available
     */
    public function testIsAvailableValid()
    {
        $quoteMock = $this->getFakeMock(CartInterface::class)
            ->setMethods(['getGrandTotal', 'getCurrency', 'getQuoteCurrencyCode'])
            ->getMockForAbstractClass();
        $quoteMock->expects($this->once())->method('getGrandTotal')->willReturn(15);
        $quoteMock->expects($this->once())->method('getCurrency')->willReturnSelf();
        $quoteMock->expects($this->once())->method('getQuoteCurrencyCode')->willReturn('EUR');

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();
        $accountConfigMock->expects($this->once())->method('getActive')->willReturn(1);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);

        $appStateMock = $this->getFakeMock(State::class)->setMethods(['getAreaCode'])->getMock();
        $appStateMock->expects($this->once())->method('getAreaCode')->willReturn('frontend');

        $eventManagerMock = $this->getFakeMock(ManagerInterface::class)->setMethods(['dispatch'])->getMock();
        $eventManagerMock->expects($this->once())->method('dispatch');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getAppState', 'getEventDispatcher'])->getMock();
        $contextMock->expects($this->once())->method('getAppState')->willReturn($appStateMock);
        $contextMock->expects($this->once())->method('getEventDispatcher')->willReturn($eventManagerMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(5))->method('getValue')
            ->withConsecutive(
                ['payment/buckaroo_magento2_test/limit_by_ip', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/max_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/min_amount', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/allowed_currencies', ScopeInterface::SCOPE_STORE, null],
                ['payment/buckaroo_magento2_test/active', ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(0, 20, 10, 'EUR,USD', true);

        $instance = $this->getInstance([
            'context' => $contextMock,
            'scopeConfig' => $scopeConfigMock,
            'configProviderFactory' => $configProviderMock
        ]);
        $result = $instance->isAvailable($quoteMock);
        $this->assertTrue($result);
    }

    public function testCanRefundParentFalse()
    {
        $instance = $this->getInstance();
        $instance->setCanRefund(false);

        $this->assertFalse($instance->canRefund());
    }

    /**
     * @dataProvider refundEnabledDisabledDataProvider
     *
     * @param $enabled
     */
    public function testCanRefundNotEnabled($enabled)
    {
        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getEnabled'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $configProviderMock->expects($this->once())->method('getEnabled')->willReturn($enabled);

        $instance = $this->getInstance(['configProviderFactory' => $configProviderMock]);
        $instance->setCanRefund(true);

        $this->assertEquals($enabled, $instance->canRefund());
    }

    public function refundEnabledDisabledDataProvider()
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }

    /**
     * @dataProvider processInvalidArgumentDataProvider
     *
     * @param $method
     */
    public function testProcessInvalidArgument($method)
    {
        $this->setExpectedException('\InvalidArgumentException');

        $paymentMock = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance();
        $instance->$method($paymentMock, 0);
    }

    public function processInvalidArgumentDataProvider()
    {
        return [
            [
                'order',
            ],
            [
                'authorize',
            ],
            [
                'capture',
            ],
            [
                'refund',
            ],
            [
                'void',
            ],
        ];
    }

    /**
     * @param $method
     * @param $canMethod
     *
     * @dataProvider cantProcessDataProvider
     */
    public function testCantProcess($method, $canMethod, $amount)
    {
        $this->setExpectedException(LocalizedException::class);

        $paymentMock = $this->getFakeMock(Payment::class, true);
        $orderModelMock = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $paymentMock->method('getOrder')->willReturn($orderModelMock);

        $instance = $this->getInstance();
        $instance->$canMethod(false);
        $instance->$method($paymentMock, $amount);
    }

    public function cantProcessDataProvider()
    {
        return [
            [
                'order',
                'setCanOrder',
                0
            ],
            [
                'authorize',
                'setCanAuthorize',
                0
            ],
            [
                'capture',
                'setCanCapture',
                0
            ],
            [
                'refund',
                'setCanRefund',
                1
            ]
        ];
    }

    public function testGetConfigDataRedirectUrl()
    {
        $expectedUrl = 'test.com';

        $instance = $this->getInstance();
        $instance->orderPlaceRedirectUrl = $expectedUrl;

        $result = $instance->getConfigData('order_place_redirect_url');

        $this->assertEquals($expectedUrl, $result);
    }

    public function testGetTitleNoConfigProvider()
    {
        $method = 'buckaroo_magento2_abstract_test';
        $expectedTitle = 'buckaroo_magento2_abstract_test_title';

        $configProviderFactoryMock = $this->getFakeMock(MethodFactory::class)->setMethods(['has'])->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($method)->willReturn(false);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')
            ->with('payment/buckaroo_magento2_test/title', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expectedTitle);

        $instance = $this->getInstance([
            'configProviderMethodFactory' => $configProviderFactoryMock,
            'scopeConfig' => $scopeConfigMock
        ]);
        $instance->buckarooPaymentMethodCode = $method;

        $result = $instance->getTitle();
        $this->assertEquals($expectedTitle, $result);
    }

    /**
     * @dataProvider getTitleNoPaymentFeeDataProvider
     *
     * @param $title
     * @param $expectedTitle
     * @param $fee
     */
    public function testGetTitlePaymentFee($title, $expectedTitle, $fee)
    {
        $method = 'buckaroo_magento2_abstract_test';

        $configProviderFactoryMock = $this->getFakeMock(MethodFactory::class)
            ->setMethods(['has', 'get', 'getPaymentFee'])
            ->getMock();
        $configProviderFactoryMock->expects($this->once())->method('has')->with($method)->willReturn(true);
        $configProviderFactoryMock->expects($this->once())->method('get')->with($method)->willReturnSelf();
        $configProviderFactoryMock->expects($this->once())->method('getPaymentFee')->willReturn($fee);

        $priceHelperMock = $this->getFakeMock(PriceHelperData::class)->setMethods(['currency'])->getMock();
        $priceHelperMock->method('currency')->with($fee, true, false)->willReturn($fee);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')
            ->with('payment/buckaroo_magento2_test/title', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($title);

        $instance = $this->getInstance([
            'configProviderMethodFactory' => $configProviderFactoryMock,
            'priceHelper' => $priceHelperMock,
            'scopeConfig' => $scopeConfigMock
        ]);
        $instance->buckarooPaymentMethodCode = $method;

        $result = $instance->getTitle();
        $this->assertEquals($expectedTitle, $result);
    }

    public function getTitleNoPaymentFeeDataProvider()
    {
        return [
            'no fee' => [
                'buckaroo_magento2_abstract_test_title',
                'buckaroo_magento2_abstract_test_title',
                false,
            ],
            'fixed fee' => [
                'buckaroo_magento2_abstract_test_title',
                'buckaroo_magento2_abstract_test_title + 5.00',
                '5.00',
            ],
            'percentage fee' => [
                'buckaroo_magento2_abstract_test_title',
                'buckaroo_magento2_abstract_test_title + 5%',
                '5%',
            ],
        ];
    }

    /**
     * @param      $method
     * @param      $setCanMethod
     * @param      $methodTransactionBuilder
     *
     * @param bool $canMethod
     *
     * @dataProvider transactionBuilderFalseTrueDataProvider
     */
    public function testTransactionBuilderFalse(
        $method,
        $setCanMethod,
        $methodTransactionBuilder,
        $canMethod = false,
        $amount = 0
    ) {
        $exceptionMessage = ucfirst($method) . ' action is not implemented for this payment method.';
        $this->setExpectedException(\LogicException::class, $exceptionMessage);

        $payment = $this->getFakeMock(Payment::class, true);

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getEnabled'])->getMock();

        $partialMock = $this->getInstance(['configProviderFactory' => $configProviderMock]);
        $orderModelMock = $this->getFakeMock(Order::class)->setMethods(['getStore','getBaseGrandTotal'])->getMock();
        $payment->method('getOrder')->willReturn($orderModelMock);

        $partialMock->$setCanMethod(true);

        if ($canMethod) {
            $configProviderMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
            $configProviderMock->expects($this->once())->method('getEnabled')->willReturn(true);
        }
        if ($method=='refund') {
            $this->markTestIncomplete(
                'This (::refundGroupTransactions()) needs to be refactored.'
            );
        }
        $partialMock->$method($payment, $amount);

        $this->assertSame($payment, $partialMock->payment);
    }

    public function transactionBuilderFalseTrueDataProvider()
    {
        return [
            [
                'order',
                'setCanOrder',
                'getOrderTransactionBuilder',
                0,
            ],
            [
                'authorize',
                'setCanAuthorize',
                'getAuthorizeTransactionBuilder',
                0,
            ],
            [
                'capture',
                'setCanCapture',
                'getCaptureTransactionBuilder',
                0,
            ],
            [
                'refund',
                'setCanRefund',
                'getRefundTransactionBuilder',
                1,
                'canRefund',
            ],
            [
                'void',
                'setCanVoid',
                'getVoidTransactionBuilder',
                0,
            ],
        ];
    }

    /**
     * @param $method
     * @param $setCanMethod
     * @param $methodTransactionBuilder
     *
     * @param $canMethod
     *
     * @dataProvider transactionBuilderFalseTrueDataProvider
     */
    public function testTransactionBuilderTrue(
        $method,
        $setCanMethod,
        $methodTransactionBuilder,
        $amount = 0,
        $canMethod = false
    ) {
        $payment = $this->getFakeMock(Payment::class, true);

        $stubbedMethods = [$methodTransactionBuilder];

        if ($canMethod) {
            $stubbedMethods[] = $canMethod;
        }
        $partialMock = $this->getFakeMock(AbstractMethodMock::class)->setMethods($stubbedMethods)->getMock();
        $loggerMock = $this->getFakeMock(BuckarooLog::class)->setMethods(['addDebug','__destruct'])->getMock();

        $partialMockReflection = new \ReflectionClass($partialMock);
        $reflectionLogger2 = $partialMockReflection->getProperty('logger2');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $loggerMock);

        $helperMock = $this->getFakeMock(HelperData::class)->setMethods(['getMode'])->getMock();
        $helperMock->method('getMode')->willReturn($helperMock);
        $partialMock->helper = $helperMock;

        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods(['setMode'])
            ->getMockForAbstractClass();
        $reflectionLogger2 = $partialMockReflection->getProperty('gateway');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $gatewayMock);

        #$partialMock->gateway = $gatewayMock;

        $partialMock->$setCanMethod(true);
        $partialMock->expects($this->once())->method($methodTransactionBuilder)->with($payment)->willReturn(true);

        if ($canMethod) {
            $partialMock->expects($this->any())
                ->method($canMethod)
                ->withAnyParameters()
                ->willReturn(true);
        }
        if ($method == 'refund') {
            $this->markTestIncomplete(
                'Skip, ::refundGroupTransactions() needs to be refactored'
            );
        }
        $orderModelMock = $this->getFakeMock(Order::class)->setMethods(['getStore','getBaseGrandTotal'])->getMock();
        $payment->method('getOrder')->willReturn($orderModelMock);

        $this->assertEquals($partialMock, $partialMock->$method($payment, $amount));
        $this->assertSame($payment, $partialMock->payment);
    }

    /**
     * @param      $method
     * @param      $setCanMethod
     * @param      $methodTransactionBuilder
     * @param      $methodTransaction
     * @param      $afterMethodEvent
     * @param bool $canMethod
     *
     * @dataProvider fullFlowDataProvider
     */
    public function testFullFlow(
        $method,
        $setCanMethod,
        $methodTransactionBuilder,
        $methodTransaction,
        $afterMethodEvent,
        $canMethod = false
    ) {
        $amount = 0;
        if ($method == 'refund') {
            $amount = 1;
            $this->markTestIncomplete(
                'Skip, ::refundGroupTransactions() needs to be refactored'
            );
        }
        $responseObject = new \stdClass();
        $response = [$responseObject];
        $orderModelMock = $this->getFakeMock(Order::class)
            ->setMethods(['getStore','getId','getBaseGrandTotal'])->getMock();
        $payment = $this->getFakeMock(Payment::class)
            ->setMethods(['getCurrencyCode', 'setIsFraudDetected','getOrder'])->getMock();
        $payment->method('getOrder')->willReturn($orderModelMock);
        $transaction = $this->getFakeMock(Transaction::class, true);
        $registryMock = $this->getFakeMock(Registry::class)->setMethods(['register'])->getMock();

        if ($method != 'refund' && $method != 'void') {
            $registryMock->expects($this->once())->method('register')->with('buckaroo_response', $response);
        }

        $transactionBuilderMock = $this->getFakeMock(TransactionBuilderInterface::class)
            ->setMethods(['setAmount', 'build'])
            ->getMockForAbstractClass();

        if ($method == 'refund') {
            $transactionBuilderMock->expects($this->once())
                ->method('setAmount')
                ->with($amount)
                ->willReturn($transaction);
        }
        $transactionBuilderMock->expects($this->once())->method('build')->willReturn($transaction);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getActive'])->getMock();

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        
        if(in_array($method,['order','authorize'])){
            $configProviderMock->expects($this->once())->method('get')->with('account')->willReturn($accountConfigMock);
        }

        $stubbedMethods = [$methodTransaction, $methodTransactionBuilder];

        if ($canMethod) {
            $stubbedMethods[] = $canMethod;
        }

        $eventManagerMock = $this->getFakeMock(ManagerInterface::class)
            ->setMethods(['dispatch'])
            ->getMockForAbstractClass();
        $eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with($afterMethodEvent, ['payment' => $payment, 'response' => $response]);

        $configMethodProviderMock = $this->getFakeMock(MethodFactory::class)
            ->setMethods(['get', 'getAllowedCurrencies'])
            ->getMock();

        if ($method == 'authorize') {
            $configMethodProviderMock->method('get')->willReturnSelf();
        }

        $partialMock = $this->getFakeMock(AbstractMethodMock::class)->setMethods($stubbedMethods)->getMock();
        $loggerMock = $this->getFakeMock(BuckarooLog::class)->setMethods(['addDebug','__destruct'])->getMock();

        $partialMockReflection = new \ReflectionClass($partialMock);
        $reflectionLogger2 = $partialMockReflection->getProperty('logger2');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $loggerMock);
        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods(['setMode'])
            ->getMockForAbstractClass();
        $reflectionLogger2 = $partialMockReflection->getProperty('gateway');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $gatewayMock);

        $helperMock = $this->getFakeMock(HelperData::class)
            ->setMethods(['getMode','setRestoreQuoteLastOrder'])->getMock();
        $helperMock->method('getMode')->willReturn($helperMock);
        $partialMock->helper = $helperMock;

        $partialMock->configProviderFactory = $configProviderMock;
        $partialMock->configProviderMethodFactory = $configMethodProviderMock;
        $this->setProperty('_registry', $registryMock, $partialMock);
        $this->setProperty('_eventManager', $eventManagerMock, $partialMock);

        $partialMock->$setCanMethod(true);

        $partialMock->expects($this->once())
            ->method($methodTransactionBuilder)
            ->with($payment)
            ->willReturn($transactionBuilderMock);

        $partialMock->expects($this->once())
            ->method($methodTransaction)
            ->with($transaction)
            ->willReturn($response);

        if ($canMethod) {
            $partialMock->method($canMethod)->withAnyParameters()->willReturn(true);
        }

        $result = $partialMock->$method($payment, $amount);

        $this->assertEquals($partialMock, $result);
        $this->assertSame($payment, $partialMock->payment);
    }

    public function fullFlowDataProvider()
    {
        return [
            [
                'order',
                'setCanOrder',
                'getOrderTransactionBuilder',
                'orderTransaction',
                'buckaroo_magento2_method_order_after',
                false,
            ],
            [
                'authorize',
                'setCanAuthorize',
                'getAuthorizeTransactionBuilder',
                'authorizeTransaction',
                'buckaroo_magento2_method_authorize_after',
                false,
            ],
            [
                'capture',
                'setCanCapture',
                'getCaptureTransactionBuilder',
                'captureTransaction',
                'buckaroo_magento2_method_capture_after',
                false,
            ],
            [
                'refund',
                'setCanRefund',
                'getRefundTransactionBuilder',
                'refundTransaction',
                'buckaroo_magento2_method_refund_after',
                'canRefund',
            ],
            [
                'void',
                'setCanVoid',
                'getVoidTransactionBuilder',
                'VoidTransaction',
                'buckaroo_magento2_method_void_after',
                false,
            ],
        ];
    }

    /**
     * @param $method
     *
     * @param $gatewayMethod
     *
     * @dataProvider processTransactionDataProvider
     */
    public function testProcessTransactionResponseNotValid($method, $gatewayMethod)
    {
        $this->setExpectedException(\Buckaroo\Magento2\Exception::class);

        $response = [];

        $transactionMock = $this->getFakeMock(Transaction::class)->getMockForAbstractClass();

        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods([$gatewayMethod])
            ->getMockForAbstractClass();
        $gatewayMock->expects($this->once())->method($gatewayMethod)->with($transactionMock)->willReturn($response);

        $validatorFactoryMock = $this->getFakeMock(ValidatorFactory::class)->setMethods(['get', 'validate'])->getMock();
        $validatorFactoryMock->expects($this->once())->method('get')->with('transaction_response')->willReturnSelf();
        $validatorFactoryMock->expects($this->once())->method('validate')->with($response)->willReturn(false);

        $instance = $this->getInstance([
            'gateway' => $gatewayMock,
            'validatorFactory' => $validatorFactoryMock
        ]);

        $instance->$method($transactionMock);
    }

    public function processTransactionDataProvider()
    {
        return [
            [
                'orderTransaction',
                'authorize',
            ],
            [
                'authorizeTransaction',
                'authorize',
            ],
            [
                'captureTransaction',
                'capture',
            ],
            [
                'refundTransaction',
                'refund',
            ],
            [
                'voidTransaction',
                'void',
            ],
        ];
    }

    /**
     * @param $method
     *
     * @param $gatewayMethod
     *
     * @dataProvider processTransactionDataProvider
     */
    public function testProcessTransactionResponseStatusNotValid($method, $gatewayMethod)
    {
        $this->setExpectedException(\Buckaroo\Magento2\Exception::class);

        $response = [];

        $transactionMock = $this->getFakeMock(Transaction::class)->getMock();

        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods([$gatewayMethod])
            ->getMockForAbstractClass();
        $gatewayMock->expects($this->once())->method($gatewayMethod)->with($transactionMock)->willReturn($response);

        $validatorFactoryMock = $this->getFakeMock(ValidatorFactory::class)->setMethods(['get', 'validate'])->getMock();
        $validatorFactoryMock->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['transaction_response'], ['transaction_response_status'])
            ->willReturnSelf();
        $validatorFactoryMock->expects($this->exactly(2))
            ->method('validate')->with($response)
            ->willReturnOnConsecutiveCalls(true, false);

        $instance = $this->getInstance([
            'gateway' => $gatewayMock,
            'validatorFactory' => $validatorFactoryMock
        ]);

        $instance->$method($transactionMock);
    }

    /**
     * @param $method
     *
     * @param $gatewayMethod
     *
     * @dataProvider processTransactionDataProvider
     */
    public function testProcessTransactionSuccessful($method, $gatewayMethod)
    {
        $response = ['test_response'];

        $transactionMock = $this->getFakeMock(Transaction::class)->getMock();

        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods([$gatewayMethod])
            ->getMockForAbstractClass();
        $gatewayMock->expects($this->once())->method($gatewayMethod)->with($transactionMock)->willReturn($response);

        $validatorFactoryMock = $this->getFakeMock(ValidatorFactory::class)->setMethods(['get', 'validate'])->getMock();
        $validatorFactoryMock->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['transaction_response'], ['transaction_response_status'])
            ->willReturnSelf();
        $validatorFactoryMock->expects($this->exactly(2))->method('validate')->with($response)->willReturn(true);

        $instance = $this->getInstance([
            'gateway' => $gatewayMock,
            'validatorFactory' => $validatorFactoryMock
        ]);

        $this->assertSame($response, $instance->$method($transactionMock));
    }

    public function testCancel()
    {
        $payment = $this->getFakeMock(Payment::class, true);

        $partialMock = $this->getFakeMock(AbstractMethodMock::class)
            ->setMethods(['getVoidTransactionBuilder'])
            ->getMock();
        $partialMock->expects($this->once())->method('getVoidTransactionBuilder')->with($payment)->willReturn(true);
        $loggerMock = $this->getFakeMock(BuckarooLog::class)->setMethods(['addDebug','__destruct'])->getMock();
        $partialMockReflection = new \ReflectionClass($partialMock);
        $reflectionLogger2 = $partialMockReflection->getProperty('logger2');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $loggerMock);
        $gatewayMock = $this->getFakeMock(GatewayInterface::class)
            ->setMethods(['setMode'])
            ->getMockForAbstractClass();
        $reflectionLogger2 = $partialMockReflection->getProperty('gateway');
        $reflectionLogger2->setAccessible(true);
        $reflectionLogger2->setValue($partialMock, $gatewayMock);

        $helperMock = $this->getFakeMock(HelperData::class)->setMethods(['getMode'])->getMock();
        $helperMock->method('getMode')->willReturn($helperMock);
        $partialMock->helper = $helperMock;

        $orderModelMock = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $payment->method('getOrder')->willReturn($orderModelMock);

        $this->assertSame($partialMock, $partialMock->cancel($payment));
    }

    public function testGetTransactionAdditionalInfo()
    {
        $data = [];

        $helperMock = $this->getFakeMock(HelperData::class)
            ->setMethods(['getMode', 'getTransactionAdditionalInfo'])
            ->getMock();
        $helperMock->expects($this->once())->method('getMode')->willReturn(HelperData::MODE_TEST);
        $helperMock->expects($this->once())->method('getTransactionAdditionalInfo')->with($data)->willReturn([]);
        $loggerMock = $this->getFakeMock(BuckarooLog::class)->setMethods(['addDebug','__destruct'])->getMock();
        $instance = $this->getInstance(['helper' => $helperMock,'logger2'=>$loggerMock]);

        $result = $instance->getTransactionAdditionalInfo($data);
        $this->assertInternalType('array', $result);
    }

    public function testSaveTransactionDataResponseKeyEmpty()
    {
        $response = new \stdClass();
        $paymentMock = $this->getFakeMock(Payment::class, true);

        $instance = $this->getInstance();

        $result = $instance->saveTransactionData($response, $paymentMock, true, false);
        $this->assertEquals($paymentMock, $result);
    }

    /**
     * @param $close
     * @param $saveId
     *
     * @dataProvider saveTransactionDataDataProvider
     */
    public function testSaveTransactionData($close, $saveId)
    {
        $response = new \stdClass();
        $key = 'test_transaction_key';
        $response->Key = $key;

        $arrayResponse = json_decode(json_encode($response), true);

        $payment = $this->getFakeMock(Payment::class)
            ->setMethods([
                'setTransactionAdditionalInfo',
                'setIsTransactionClosed',
                'setTransactionId',
                'setAdditionalInformation',
                'getMethodInstance'
            ])
            ->getMock();

        $payment->expects($this->once())
            ->method('setTransactionAdditionalInfo')
            ->with(PaymentTransaction::RAW_DETAILS, $arrayResponse);

        $payment->expects($this->once())->method('setIsTransactionClosed')->with($close);
        $payment->expects($this->once())->method('setTransactionId')->with($key);

        if ($saveId) {
            $payment->expects($this->once())
                ->method('setAdditionalInformation')
                ->with(AbstractMethodMock::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $key);
        }

        $methodInstanceMock = $this->getFakeMock(MethodInterface::class)
            ->setMethods(['processCustomPostData'])
            ->getMockForAbstractClass();
        $methodInstanceMock->expects($this->once())->method('processCustomPostData')->with($payment, $response);

        $payment->expects($this->once())->method('getMethodInstance')->willReturn($methodInstanceMock);

        $helperMock = $this->getFakeMock(HelperData::class)
            ->setMethods(['getMode', 'getTransactionAdditionalInfo'])
            ->getMock();
        $helperMock->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->with($arrayResponse)
            ->willReturn($arrayResponse);
        $helperMock->expects($this->once())->method('getMode')->willReturn(0);

        $instance = $this->getInstance(['helper' => $helperMock]);

        $result = $instance->saveTransactionData($response, $payment, $close, $saveId);
        $this->assertInstanceOf(Payment::class, $result);
    }

    public function saveTransactionDataDataProvider()
    {
        return [
            [
                true,
                true,
            ],
            [
                true,
                false,
            ],
            [
                false,
                true,
            ],
            [
                false,
                false,
            ],
        ];
    }

    public function addExtraFieldsProvider()
    {
        return [
            'with creditmemo params' => [
                [
                    'creditmemo' => ['code-1' => 'value-2']
                ],
                [
                    ['code' => 'code-1']
                ],
                [
                    'RequestParameter' => [
                        [
                            '_' => 'value-2',
                            'Name' => 'code-1',
                        ],
                    ],
                ]
            ],
            'creditmemo params no value' => [
                [
                    'creditmemo' => ['buckaroo_code' => null]
                ],
                [
                    ['code' => 'buckaroo_code']
                ],
                [
                    'RequestParameter' => [
                        [
                            '_' => '',
                            'Name' => 'buckaroo_code',
                        ],
                    ],
                ]
            ],
            'creditmemo params no code' => [
                [
                    'creditmemo' => []
                ],
                [
                    ['code' => 'buckaroo_code']
                ],
                []
            ],
            'no creditmemo params' => [
                [
                    'transaction' => ['123qwerty456']
                ],
                [
                    ['code' => 'buckaroo_code']
                ],
                []
            ],
            'no params at all' => [
                [],
                [
                    ['code' => 'buckaroo_code']
                ],
                []
            ],
            'no extra fields' => [
                [
                    'creditmemo' => ['refund_code' => '123']
                ],
                [],
                []
            ]
        ];
    }

    /**
     * @param $params
     * @param $extraFields
     * @param $expected
     *
     * @dataProvider addExtraFieldsProvider
     */
    public function testAddExtraFields($params, $extraFields, $expected)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)
            ->setMethods(['getParams'])->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParams')->willReturn($params);

        $refundFactoryMock = $this->getFakeMock(RefundFieldsFactory::class)->setMethods(['get'])->getMock();

        $instance = $this->getInstance([
            'refundFieldsFactory' => $refundFactoryMock,
            'request' => $requestMock
        ]);

        $refundFactoryMock->method('get')->with($instance->getCode())->willReturn($extraFields);

        $this->assertEquals($expected, $instance->addExtraFields($instance->getCode()));
    }

    public function testCreateCreditNoteRequest()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getAdditionalInformation', 'setAdditionalInformation','getOrder'])
            ->getMock();
        $infoInstanceMock->expects($this->exactly(3))
            ->method('getAdditionalInformation')
            ->withConsecutive(
                ['buckaroo_cm3_invoice_key'],
                [AbstractMethodMock::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY],
                ['buckaroo_failed_authorize']
            )
            ->willReturnOnConsecutiveCalls('abc', 'def', 1);
        $infoInstanceMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(AbstractMethodMock::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, 'def');

        $helperMock = $this->getFakeMock(HelperData::class)->setMethods(['getMode'])->getMock();
        $helperMock->method('getMode')->willReturn($helperMock);

        $orderModelMock = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $infoInstanceMock->method('getOrder')->willReturn($orderModelMock);

        $instance = $this->getInstance(['helper'=>$helperMock]);
        $result = $instance->createCreditNoteRequest($infoInstanceMock);
        $this->assertInstanceOf(AbstractMethodMock::class, $result);
    }

    public function addToRegistryProvider()
    {
        return [
            'single data, empty registry' => [
                ['some data'],
                null,
                ['some data']
            ],
            'multiple data, empty registry' => [
                ['string data', ['array data'], 12345],
                null,
                ['string data', ['array data'], 12345]
            ],
            'no data, empty registry' => [
                [],
                null,
                null
            ],
            'null data, empty registry' => [
                [null],
                null,
                [null]
            ],
            'single data, filled registry' => [
                ['some data'],
                ['existing data'],
                ['existing data', 'some data']
            ],
            'multiple data, filled registry' => [
                ['string data', ['array data'], 12345],
                [987258, (Object)['existing array']],
                [987258, (Object)['existing array'], 'string data', ['array data'], 12345]
            ],
            'no data, filled registry' => [
                [],
                [987258, 'existing data', (Object)['existing array']],
                [987258, 'existing data', (Object)['existing array']]
            ],
            'null data, filled registry' => [
                [null],
                ['buckaroo 123'],
                ['buckaroo 123', null]
            ],
        ];
    }

    /**
     * @param $newData
     * @param $registryData
     * @param $expected
     *
     * @dataProvider addToRegistryProvider
     */
    public function testAddToRegistry($newData, $registryData, $expected)
    {
        $registryMock = $this->getFakeMock(Registry::class)->setMethods(null)->getMock();
        $registryMock->register('buckaroo_registry_key', $registryData);

        $instance = $this->getInstance(['registry' => $registryMock]);

        foreach ($newData as $data) {
            $this->invokeArgs('addToRegistry', ['buckaroo_registry_key', $data], $instance);
        }

        $result = $registryMock->registry('buckaroo_registry_key');
        $this->assertEquals($expected, $result);
    }
}
