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
namespace TIG\Buckaroo\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Mockery as m;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Observer\InvoiceRegister;

class InvoiceRegisterTest extends BaseTest
{
    /**
     * @var InvoiceRegister
     */
    protected $object;

    /**
     * @var m\MockInterface|Observer
     */
    protected $observer;

    /**
     * Setup the basic mock object.
     */
    public function setUp()
    {
        parent::setUp();

        $this->object = $this->objectManagerHelper->getObject(InvoiceRegister::class);
        $this->observer = m::mock(Observer::class);
        $this->observer->shouldReceive('getEvent', 'getInvoice')->once()->andReturnSelf();
    }

    /**
     * Test the happy path. Nothing is changed.
     */
    public function testInvoiceRegisterHappyPath()
    {
        $this->observer->shouldReceive('getBaseBuckarooFee')->once()->andReturn(false);

        $result = $this->object->execute($this->observer);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }

    /**
     * Test that the payment fee isset.
     */
    public function testInvoiceRegisterWithPaymentFee()
    {
        $this->observer->shouldReceive('getBaseBuckarooFee')->once()->andReturn(true);
        $this->observer->shouldReceive(
            'getBuckarooFee',
            'getBaseBuckarooFee',
            'getBuckarooFeeTaxAmount',
            'getBuckarooFeeBaseTaxAmount',
            'getBuckarooFeeInclTax',
            'getBaseBuckarooFeeInclTax'
        )->once();

        $order = m::mock();

        $order->shouldReceive('setBuckarooFeeInvoiced')->once();
        $order->shouldReceive('setBaseBuckarooFeeInvoiced')->once();
        $order->shouldReceive('setBuckarooFeeTaxAmountInvoiced')->once();
        $order->shouldReceive('setBuckarooFeeBaseTaxAmountInvoiced')->once();
        $order->shouldReceive('setBuckarooFeeInclTaxInvoiced')->once();
        $order->shouldReceive('setBaseBuckarooFeeInclTaxInvoiced')->once();

        $order->shouldReceive('getBuckarooFeeInvoiced')->once();
        $order->shouldReceive('getBaseBuckarooFeeInvoiced')->once();
        $order->shouldReceive('getBuckarooFeeTaxAmountInvoiced')->once();
        $order->shouldReceive('getBuckarooFeeBaseTaxAmountInvoiced')->once();
        $order->shouldReceive('getBuckarooFeeInclTaxInvoiced')->once();
        $order->shouldReceive('getBaseBuckarooFeeInclTaxInvoiced')->once();

        $this->observer->shouldReceive('getOrder')->once()->andReturn($order);

        $result = $this->object->execute($this->observer);
        $this->assertInstanceOf(InvoiceRegister::class, $result);
    }
}
