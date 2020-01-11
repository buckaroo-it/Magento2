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
namespace TIG\Buckaroo\Test\Unit\Gateway\Http\TransactionBuilder;

use Magento\Sales\Model\Order;
use TIG\Buckaroo\Gateway\Http\Transaction;
use TIG\Buckaroo\Test\BaseTest;

class AbstractTransactionBuilderTest extends BaseTest
{
    protected $instanceClass = AbstractTransactionBuilderMock::class;

    /**
     * @var \TIG\Buckaroo\Gateway\Http\TransactionBuilder\AbstractTransactionBuilderMock
     */
    protected $object;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Account|\Mockery\MockInterface
     */
    protected $configProviderAccount;

    public function setUp()
    {
        parent::setUp();

        $this->configProviderAccount = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\Account::class);

        $this->object = $this->objectManagerHelper
            ->getObject(
                AbstractTransactionBuilderMock::class,
                ['configProviderAccount' => $this->configProviderAccount]
            );
    }

    public function testOriginalTransactionKey()
    {
        $value = 'testString';
        $this->object->setOriginalTransactionKey($value);

        $this->assertEquals($value, $this->object->getOriginalTransactionKey());
    }

    public function testChannel()
    {
        $value = 'testString';
        $this->object->setChannel($value);

        $this->assertEquals($value, $this->object->getChannel());
    }

    public function testAmount()
    {
        $value = 'testString';
        $this->object->setAmount($value);

        $this->assertEquals($value, $this->object->getAmount());
    }

    public function testInvoiceId()
    {
        $value = 'testString';
        $this->object->setInvoiceId($value);

        $this->assertEquals($value, $this->object->getInvoiceId());
    }

    public function testCurrency()
    {
        $value = 'testString';
        $this->object->setCurrency($value);

        $this->assertEquals($value, $this->object->getCurrency());
    }

    public function testOrder()
    {
        $value = 'testString';
        $this->object->setOrder($value);

        $this->assertEquals($value, $this->object->getOrder());
    }

    public function testServices()
    {
        $value = 'testString';
        $this->object->setServices($value);

        $this->assertEquals($value, $this->object->getServices());
    }

    public function testCustomVars()
    {
        $value = 'testString';
        $this->object->setCustomVars($value);

        $this->assertEquals($value, $this->object->getCustomVars());
    }

    public function testMethod()
    {
        $value = 'testString';
        $this->object->setMethod($value);

        $this->assertEquals($value, $this->object->getMethod());
    }

    public function testType()
    {
        $value = 'testString';
        $this->object->setType($value);

        $this->assertEquals($value, $this->object->getType());
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
        $this->configProviderAccount->shouldReceive('getMerchantKey')->once()->andReturn($merchantKey);

        $order = \Mockery::mock(Order::class);
        $order->shouldReceive('getStore')->once();

        $this->object->setOrder($order);

        $result = $this->object->GetHeaders();

        $this->assertCount(2, $result);
        $this->assertEquals('https://checkout.buckaroo.nl/PaymentEngine/', $result[0]->namespace);
        $this->assertEquals($merchantKey, $result[0]->data['WebsiteKey']);

        foreach ($result as $header) {
            $this->assertInstanceOf(\SoapHeader::class, $header);
        }
    }
}
