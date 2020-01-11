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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider;

use Magento\Store\Model\ScopeInterface;
use Mockery as m;
use Magento\Framework\App\Config\ScopeConfigInterface;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Account;

class AccountTest extends BaseTest
{
    /**
     * @var Account
     */
    protected $object;

    /**
     * @var ScopeConfigInterface|m\MockInterface
     */
    protected $scopeConfig;

    /**
     * Setup the mock objects.
     */
    public function setUp()
    {
        parent::setUp();

        $this->scopeConfig = m::mock(ScopeConfigInterface::class);
        $this->object = $this->objectManagerHelper->getObject(
            Account::class,
            [
            'scopeConfig' => $this->scopeConfig,
            ]
        );
    }

    /**
     * Test the getConfig function.
     */
    public function testGetConfig()
    {
        $account = new \ReflectionClass(Account::class);
        $classConstants = $account->getConstants();

        foreach ($classConstants as $constant => $value) {
            $this->scopeConfig
                ->shouldReceive('getValue')
                ->once()
                ->with($value, ScopeInterface::SCOPE_STORE, null)
                ->andReturn($constant);
        }

        $results = $this->object->getConfig();

        foreach ($results as $name => $value) {
            $this->assertEquals('XPATH_ACCOUNT_' . strtoupper($name), $value);
        }
    }
}
