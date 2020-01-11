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
use TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard;
use Magento\Framework\View\Asset\Repository;

class CreditcardTest extends BaseTest
{
    /**
     * @var Creditcard
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
            Creditcard::class,
            [
                'assetRepo' => $this->assetRepository,
                'scopeConfig' => $this->scopeConfig
            ]
        );
    }

    public function testGetImageUrl()
    {
        $issuers = 'amex,visa';
        $allowedCurrencies = 'USD,EUR';
        $this->scopeConfig->shouldReceive('getValue')
            ->once()
            ->withArgs(
                [
                    Creditcard::XPATH_CREDITCARD_ALLOWED_CREDITCARDS,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ]
            )
            ->andReturn($issuers);

        $this->scopeConfig->shouldReceive('getValue')
            ->once()
            ->withArgs(
                [
                    Creditcard::XPATH_ALLOWED_CURRENCIES,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    null
                ]
            )
            ->andReturn($allowedCurrencies);

        $shouldReceive = $this->assetRepository
            ->shouldReceive('getUrl')
            ->with(\Mockery::type('string'));

        $options = $this->object->getConfig();

        $shouldReceive->times(count($options['payment']['buckaroo']['creditcard']['cards']));

        $this->assertTrue(array_key_exists('payment', $options));
        $this->assertTrue(array_key_exists('buckaroo', $options['payment']));
        $this->assertTrue(array_key_exists('creditcard', $options['payment']['buckaroo']));
        $this->assertTrue(array_key_exists('cards', $options['payment']['buckaroo']['creditcard']));
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
                    \TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard::XPATH_CREDITCARD_ACTIVE,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    null
                ]
            )
            ->andReturn('1');

        $this->assertEquals(1, $this->object->getActive());
    }
}
