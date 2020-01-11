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
use TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards;
use Magento\Framework\View\Asset\Repository;

class GiftcardsTest extends BaseTest
{
    /**
     * @var Giftcards
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

    public function setUp()
    {
        parent::setUp();

        $this->assetRepository = m::mock(Repository::class);
        $this->scopeConfig = \Mockery::mock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->object = $this->objectManagerHelper->getObject(
            Giftcards::class,
            [
            'assetRepo' => $this->assetRepository,
            'scopeConfig' => $this->scopeConfig
            ]
        );
    }

    /**
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $this->scopeConfig->shouldReceive('getValue')
            ->with('payment/tig_buckaroo_giftcards/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ->andReturn(true);
        $this->scopeConfig->shouldReceive('getValue')->andReturn(false);

        $result = $this->object->getConfig();

        $this->assertTrue(array_key_exists('payment', $result));
        $this->assertTrue(array_key_exists('buckaroo', $result['payment']));
        $this->assertTrue(array_key_exists('giftcards', $result['payment']['buckaroo']));
        $this->assertTrue(array_key_exists('paymentFeeLabel', $result['payment']['buckaroo']['giftcards']));
        $this->assertTrue(array_key_exists('allowedCurrencies', $result['payment']['buckaroo']['giftcards']));
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $this->scopeConfig->shouldReceive('getValue')->once()->andReturn(0);

        $this->assertFalse((bool) $this->object->getPaymentFee());
    }
}
