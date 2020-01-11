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

use Mockery as m;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Ideal;
use Magento\Framework\View\Asset\Repository;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class IdealTest extends BaseTest
{
    /**
     * @var iDEAL
     */
    protected $object;

    /**
     * @var m\MockInterface
     */
    protected $assetRepository;

    /**
     * @var m\MockInterface
     */
    protected $scopeConfig;

    /**
     * Setup our dependencies
     */
    public function setUp()
    {
        parent::setUp();

        $this->assetRepository = m::mock(Repository::class);
        $this->scopeConfig = m::mock(ScopeConfigInterface::class);
        $this->object = $this->objectManagerHelper->getObject(
            Ideal::class,
            [
            'assetRepo' => $this->assetRepository,
            'scopeConfig' => $this->scopeConfig,
            ]
        );
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
     * Check if the getImageUrl function is called for every record.
     */
    public function testGetImageUrl()
    {
        $this->scopeConfig->shouldReceive('getValue')->andReturn(true);

        $shouldReceive = $this->assetRepository
            ->shouldReceive('getUrl')
            ->with(\Mockery::type('string'));

        $options = $this->object->getConfig();

        $shouldReceive->times(count($options['payment']['buckaroo']['ideal']['banks']));

        $this->assertTrue(array_key_exists('payment', $options));
        $this->assertTrue(array_key_exists('buckaroo', $options['payment']));
        $this->assertTrue(array_key_exists('ideal', $options['payment']['buckaroo']));
        $this->assertTrue(array_key_exists('banks', $options['payment']['buckaroo']['ideal']));
    }

    /**
     * Check if the returned issuers list contains the necessary attributes.
     */
    public function testIssuers()
    {
        $issuers = $this->object->getIssuers();

        foreach ($issuers as $issuer) {
            $this->assertTrue(array_key_exists('name', $issuer));
            $this->assertTrue(array_key_exists('code', $issuer));
        }
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $this->scopeConfig->shouldReceive('getValue')->once()->andReturn(0);

        $this->assertFalse((bool) $this->object->getPaymentFee());
    }

    /**
     * Check if the payment free is return as a float.
     */
    public function testGetPaymentFeeReturnNumber()
    {
        $this->scopeConfig->shouldReceive('getValue')->once()->andReturn('10');

        $this->assertEquals(10, $this->object->getPaymentFee());
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $this->scopeConfig->shouldReceive('getValue')
            ->once()
            ->withArgs([Ideal::XPATH_IDEAL_ACTIVE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null])
            ->andReturn('1');

        $this->assertEquals(1, $this->object->getActive());
    }
}
