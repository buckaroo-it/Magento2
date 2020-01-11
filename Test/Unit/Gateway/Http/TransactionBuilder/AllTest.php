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

use Magento\Framework\Url;
use Mockery as m;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Refund;

class AllTest extends BaseTest
{
    /**
     * @var \Magento\Sales\Model\Order|m\MockInterface
     */
    protected $order;

    /**
     * @var Order|Refund
     */
    protected $object;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Account|m\MockInterface
     */
    protected $configProviderAccount;

    /**
     * @var Url|\Mockery\MockInterface
     */
    protected $urlBuilderMock;

    /**
     * Setup the required dependencies
     */
    public function setUp()
    {
        parent::setUp();

        $this->order = m::mock('Magento\Sales\Model\Order');
        $this->order->shouldReceive('save');
        $this->configProviderAccount = m::mock('\TIG\Buckaroo\Model\ConfigProvider\Account');
        $this->urlBuilderMock = m::mock(Url::class);

        $this->object = $this->objectManagerHelper->getObject(
            Order::class,
            [
                'configProviderAccount' => $this->configProviderAccount,
                'urlBuilder' => $this->urlBuilderMock
            ]
        );
    }

    /**
     * Generate a mock for the getBody method of Order and refund.
     *
     * @param array $expected
     *
     * @return $this
     */
    public function createGetBodyMock(array $expected)
    {
        $this->configProviderAccount->shouldReceive('getTransactionLabel')->andReturn($expected['Description']);
        $this->configProviderAccount->shouldReceive('getCreateOrderBeforeTransaction')->andReturn(1);
        $this->configProviderAccount->shouldReceive('getOrderStatusNew')->andReturn(1);

        $this->urlBuilderMock->shouldReceive('setScope')->andReturnSelf();
        $this->urlBuilderMock->shouldReceive('getRouteUrl')->andReturnSelf();
        $this->urlBuilderMock->shouldReceive('getDirectUrl')->andReturnSelf();

        $this->order->shouldReceive('getIncrementId')->atLeast()->times(1)->andReturn($expected['Invoice']);
        $this->order->shouldReceive('getRemoteIp')->andReturn($expected['ClientIP']['_']);
        $this->order->shouldReceive('getStore')->once();
        $this->order->shouldReceive('setState');
        $this->order->shouldReceive('setStatus');
        $this->order->shouldReceive('getStoreId')->andReturn(1);
        $this->object->setOrder($this->order);

        return $this;
    }

    /**
     * Test the getBody method of the Order.
     */
    public function testGetBodyOrder()
    {
        $expected = [
            'Currency' => 'EUR',
            'AmountDebit' => 50,
            'AmountCredit' => 0,
            'Invoice' => 999,
            'Order' => 999,
            'Description' => 'transactionLabel',
            'ClientIP' => [
                '_' => '127.0.0.1',
                'Type' => 'IPv4',
            ],
            'StartRecurrent' => 1,
            'Services' => [
                'Service' => 'servicesString',
            ],
        ];

        $this->object->amount = 50;
        $this->object->currency = 'EUR';
        $this->object->invoiceId = $expected['Invoice'];
        $this->object->setStartRecurrent($expected['StartRecurrent']);
        $this->object->setServices($expected['Services']['Service']);

        $this->createGetBodyMock($expected);

        $result = $this->object->getBody();
        foreach ($expected as $key => $value) {
            $valueToTest = $value;

            if (is_array($valueToTest)) {
                $valueToTest = (object)$value;
            }

            $this->assertEquals($valueToTest, $result[$key]);
        }
    }

    /**
     * Test the getBody method of the Refund.
     */
    public function testGetBodyRefund()
    {
        $expected = [
            'Currency' => 'EUR',
            'AmountDebit' => 0,
            'AmountCredit' => 50,
            'Invoice' => 999,
            'Order' => 999,
            'Description' => 'transactionLabel',
            'ClientIP' => [
                '_' => '127.0.0.1',
                'Type' => 'IPv4',
            ],
        ];

        /**
         * The Order class is used by default, but in this case we want to test the Refund class specifically.
         */
        $this->object = $this->objectManagerHelper->getObject(
            Refund::class,
            [
                'configProviderAccount' => $this->configProviderAccount,
                'urlBuilder' => $this->urlBuilderMock
            ]
        );

        $this->object->amount = 50;
        $this->object->currency = 'EUR';

        $this->createGetBodyMock($expected);

        $result = $this->object->getBody();
        foreach ($expected as $key => $value) {
            $valueToTest = $value;

            if (is_array($valueToTest)) {
                $valueToTest = (object)$value;
            }

            $this->assertEquals($valueToTest, $result[$key]);
        }
    }

    /**
     * Test the getters and setters for the originalTransactionKey variable.
     */
    public function testOriginalTransactionKey()
    {
        $value = 'testString';
        $this->object->setOriginalTransactionKey($value);

        $this->assertEquals($value, $this->object->getOriginalTransactionKey());
    }

    /**
     * Test the getters and setters for the channel variable.
     */
    public function testChannel()
    {
        $value = 'testString';
        $this->object->setChannel($value);

        $this->assertEquals($value, $this->object->getChannel());
    }

    /**
     * Test the getters and setters for the method variable.
     */
    public function testMethod()
    {
        $value = 'testString';
        $this->object->setMethod($value);

        $this->assertEquals($value, $this->object->getMethod());
    }

    /**
     * Test the getHeaders method. This returns an array with object of the SoapHeader class.
     */
    public function testGetHeaders()
    {
        $merchantKey = uniqid();
        $this->configProviderAccount->shouldReceive('getMerchantKey')->once()->andReturn($merchantKey);

        $order = \Mockery::mock(\Magento\Sales\Model\Order::class);
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
