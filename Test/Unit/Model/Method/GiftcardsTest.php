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
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use TIG\Buckaroo\Model\Method\Giftcards;
use TIG\Buckaroo\Test\BaseTest;

class GiftcardsTest extends BaseTest
{
    protected $instanceClass = Giftcards::class;

    public function testCanCaptureShouldReturnTrue()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn('fake_order');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertTrue($instance->canCapture());
    }

    public function testCanCaptureShouldReturnFalse()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn('order');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertFalse($instance->canCapture());
    }

    public function testIsAvailableNotAllowedGiftcards()
    {
        $gcConfigMock = $this->getFakeMock(GiftcardsConfig::class)->setMethods(['getAllowedGiftcards'])->getMock();
        $gcConfigMock->expects($this->once())->method('getAllowedGiftcards')->willReturn(null);

        $configFactoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configFactoryMock->expects($this->once())->method('get')->with('giftcards')->willReturn($gcConfigMock);

        $quoteMock = $this->getFakeMock(CartInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['configProviderMethodFactory' => $configFactoryMock]);

        $this->assertFalse($instance->isAvailable($quoteMock));
    }

    public function testGetOrderTransactionBuilder()
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $orderMock->expects($this->once())->method('getStore')->willReturn(0);

        $fixture = [
            'allowed_giftcards' => 'bookgiftcard,webshopgiftcard',
            'order' => $orderMock
        ];

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->method('getOrder')->willReturn($fixture['order']);

        $orderMock =$this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setCustomVars'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomVars')->willReturnCallback(
            function ($customVars) use ($fixture, $orderMock) {
                $this->assertEquals(
                    $fixture['allowed_giftcards'] . ',ideal',
                    $customVars['ServicesSelectableByClient']
                );
                $this->assertEquals('RedirectToHTML', $customVars['ContinueOnIncomplete']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(GiftcardsConfig::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS, ScopeInterface::SCOPE_STORE)
            ->willReturn($fixture['allowed_giftcards']);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance([
            'scopeConfig' => $scopeConfigMock,
            'transactionBuilderFactory' => $trxFactoryMock
        ]);

        $instance->setData('info_instance', $infoInterface);
        $this->assertEquals($orderMock, $instance->getOrderTransactionBuilder($paymentMock));
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $fixture = [
            'name' => 'giftcards',
            'action' => 'Authorize',
            'order' => 'order'
        ];

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);

        $orderMock =$this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setServices'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['name'], $services['Name']);
                $this->assertEquals($fixture['action'], $services['Action']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);

        $instance->setData('info_instance', $infoInterface);
        $this->assertEquals($orderMock, $instance->getAuthorizeTransactionBuilder($paymentMock));
    }

    public function testGetCaptureTransactionBuilder()
    {
        $fixture = [
            'name' => 'giftcards',
            'action' => 'Capture',
            'transaction_key' => 'key!'
        ];

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(['getBaseGrandTotal'])->getMock();
        $invoiceMock->expects($this->once())->method('getBaseGrandTotal')->willReturn(25);

        $invoiceCollection = $this->objectManagerHelper->getCollectionMock(Collection::class, [$invoiceMock]);
        $invoiceCollection->expects($this->once())->method('count')->willReturn(1);

        $paymentOrderMock = $this->getFakeMock(MagentoOrder::class)
            ->setMethods(['getBaseGrandTotal', 'hasInvoices', 'getInvoiceCollection'])
            ->getMock();
        $paymentOrderMock->expects($this->once())->method('getBaseGrandTotal')->willReturn(25);
        $paymentOrderMock->expects($this->exactly(2))->method('getInvoiceCollection')->willReturn($invoiceCollection);

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->exactly(2))->method('getOrder')->willReturn($paymentOrderMock);
        $paymentMock->expects($this->once())->method('getAdditionalInformation')
            ->with(Giftcards::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn($fixture['transaction_key']);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['setOrder', 'setMethod', 'setChannel', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($paymentOrderMock)->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setChannel')->with('CallCenter')->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with($fixture['transaction_key'])
            ->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['name'], $services['Name']);
                $this->assertEquals($fixture['action'], $services['Action']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getCaptureTransactionBuilder($paymentMock));
    }

    public function testGetRefundTransactionBuilder()
    {
        $instance = $this->getInstance();
        $this->assertFalse($instance->getRefundTransactionBuilder(null));
    }

    public function testGetVoidTransactionBuilder()
    {
        $instance = $this->getInstance();
        $this->assertTrue($instance->getVoidTransactionBuilder(null));
    }
}
