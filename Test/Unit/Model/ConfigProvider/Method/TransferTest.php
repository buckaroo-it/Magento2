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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\ConfigProvider\Method\Transfer;

class TransferTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Transfer::class;

    /**
     * @var Transfer
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $scopeConfig;

    /**
     * Setup our dependencies
     */
    public function setUp()
    {
        parent::setUp();

        $this->scopeConfig = \Mockery::mock(ScopeConfigInterface::class);
        $this->object = $this->objectManagerHelper->getObject(
            Transfer::class,
            [
                'scopeConfig' => $this->scopeConfig,
            ]
        );
    }

    /**
     * Helper function to set the return value fromt the getValue method.
     *
     * @param $value
     *
     * @return $this
     */
    protected function paymentFeeConfig($value)
    {
        $this->scopeConfig
            ->shouldReceive('getValue')
            ->with(
                Transfer::XPATH_TRANSFER_PAYMENT_FEE,
                ScopeInterface::SCOPE_STORE
            )
            ->andReturn($value);

        return $this;
    }

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn(false);

        $this->assertEquals([], $this->object->getConfig());
    }

    /**
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $sendEmail = '1';
        $this->scopeConfig->shouldReceive('getValue')
            ->with('payment/tig_buckaroo_transfer/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->andReturn(true);
        $this->scopeConfig->shouldReceive('getValue')
            ->withArgs(
                [
                    'payment/tig_buckaroo_transfer/send_email',
                    ScopeInterface::SCOPE_STORE,
                ]
            )
            ->once()
            ->andReturn($sendEmail);
        $this->scopeConfig->shouldReceive('getValue')->andReturn(false);

        $result = $this->object->getConfig();

        $this->assertTrue(array_key_exists('payment', $result));
        $this->assertTrue(array_key_exists('buckaroo', $result['payment']));
        $this->assertTrue(array_key_exists('transfer', $result['payment']['buckaroo']));
        $this->assertEquals($sendEmail, $result['payment']['buckaroo']['transfer']['sendEmail']);
    }

    /**
     * @return array
     */
    public function getSendMailProvider()
    {
        return [
            'Do not send mail' => [
                '0',
                'false'
            ],
            'Send mail' => [
                '1',
                'true'
            ]
        ];
    }

    /**
     * @param $value
     * @param $expected
     *
     * @dataProvider getSendMailProvider
     */
    public function testGetSendMail($value, $expected)
    {
        $scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Transfer::XPATH_TRANSFER_SEND_EMAIL, ScopeInterface::SCOPE_STORE)
            ->willReturn($value);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getSendEmail();

        $this->assertEquals($expected, $result);
    }

    /**
     * Test what is returned by the getPaymentFee method with a value of 10
     */
    public function testGetPaymentFee()
    {
        $value = '10';
        $this->paymentFeeConfig($value);

        $this->assertEquals($value, $this->object->getPaymentFee());
    }

    /**
     * Test what is returned by the getPaymentFee when not set
     */
    public function testGetPaymentFeeNull()
    {
        $value = null;
        $this->paymentFeeConfig($value);

        $this->assertFalse((bool) $this->object->getPaymentFee());
    }

    /**
     * Test what is returned by the getPaymentFee method when it is negative
     */
    public function testGetPaymentFeeNegative()
    {
        $value = '-10';
        $this->paymentFeeConfig($value);

        $this->assertEquals($value, $this->object->getPaymentFee());
    }

    /**
     * Test what is returned by the getPaymentFee method when the config value is empty
     */
    public function testGetPaymentFeeEmpty()
    {
        $value = '';
        $this->paymentFeeConfig($value);

        $this->assertFalse((bool) $this->object->getPaymentFee());
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $this->scopeConfig->shouldReceive('getValue')
            ->once()
            ->withArgs(
                [
                                  Transfer::XPATH_TRANSFER_ACTIVE,
                                  ScopeInterface::SCOPE_STORE,
                                  null
                              ]
            )
            ->andReturn('1');

        $this->assertEquals(1, $this->object->getActive());
    }
}
