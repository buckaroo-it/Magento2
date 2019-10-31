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
namespace TIG\Buckaroo\Test\Unit\Gateway\Http\TransactionBuilder;

use Magento\Sales\Model\Order;
use TIG\Buckaroo\Gateway\Http\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Test\BaseTest;

class AbstractTransactionBuilderTest extends BaseTest
{
    protected $instanceClass = AbstractTransactionBuilderMock::class;

    public function testOriginalTransactionKey()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setOriginalTransactionKey($value);

        $this->assertEquals($value, $instance->getOriginalTransactionKey());
    }

    public function testChannel()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setChannel($value);

        $this->assertEquals($value, $instance->getChannel());
    }

    public function testAmount()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setAmount($value);

        $this->assertEquals($value, $instance->getAmount());
    }

    public function testInvoiceId()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setInvoiceId($value);

        $this->assertEquals($value, $instance->getInvoiceId());
    }

    public function testCurrency()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setCurrency($value);

        $this->assertEquals($value, $instance->getCurrency());
    }

    public function testOrder()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setOrder($value);

        $this->assertEquals($value, $instance->getOrder());
    }

    public function testServices()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setServices($value);

        $this->assertEquals($value, $instance->getServices());
    }

    public function testCustomVars()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setCustomVars($value);

        $this->assertEquals($value, $instance->getCustomVars());
    }

    public function testMethod()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setMethod($value);

        $this->assertEquals($value, $instance->getMethod());
    }

    public function testType()
    {
        $value = 'testString';
        $instance = $this->getInstance();
        $instance->setType($value);

        $this->assertEquals($value, $instance->getType());
    }

    public function testReturnUrl()
    {
        $value = 'testString';
        $urlBuilderMock = $this->getFakeMock(\Magento\Framework\Url::class)->setMethods(['getRouteUrl'])->getMock();
        $urlBuilderMock->method('getRouteUrl')->willReturn($value);

        $instance = $this->getInstance(['urlBuilder' => $urlBuilderMock]);
        $instance->setReturnUrl($value);

        $this->assertEquals($value, $instance->getReturnUrl());
    }

    public function testBuild()
    {
        $transactionMock = $this->getMockBuilder(Transaction::class)->setMethods(null)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $orderMock->expects($this->atLeastOnce())->method('getStore');

        $instance = $this->getInstance(['transaction' => $transactionMock]);
        $instance->setOrder($orderMock);
        $result = $instance->build();

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertInternalType('array', $result->getBody());
        $this->assertInternalType('array', $result->getHeaders());
    }

    public function testGetHeaders()
    {
        $merchantKey = uniqid();

        $configProviderAccountMock = $this->getFakeMock(Account::class)->setMethods(['getMerchantKey'])->getMock();
        $configProviderAccountMock->expects($this->once())->method('getMerchantKey')->willReturn($merchantKey);

        $order = $this->getFakeMock(Order::class)->setMethods(['getStore'])->getMock();
        $order->expects($this->once())->method('getStore');

        $instance = $this->getInstance(['configProviderAccount' => $configProviderAccountMock]);
        $instance->setOrder($order);

        $result = $instance->GetHeaders();

        $this->assertCount(2, $result);
        $this->assertEquals('https://checkout.buckaroo.nl/PaymentEngine/', $result[0]->namespace);
        $this->assertEquals($merchantKey, $result[0]->data['WebsiteKey']);

        foreach ($result as $header) {
            $this->assertInstanceOf(\SoapHeader::class, $header);
        }
    }
}
