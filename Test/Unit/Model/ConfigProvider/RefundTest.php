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

class RefundTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\Mockery\MockInterface
     */
    protected $scopeConfig;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Refund
     */
    protected $object;

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->scopeConfig = \Mockery::mock(\Magento\Framework\App\Config\ScopeConfigInterface::class)->makePartial();

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\ConfigProvider\Refund::class,
            [
            'scopeConfig' => $this->scopeConfig,
            ]
        );
    }

    /**
     * Test the getConfig method.
     */
    public function testGetConfig()
    {
        $fixture = [
            \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH => false,
            \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED => false,
        ];

        $this->scopeConfig->shouldReceive('getValue')
            ->with(
                \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                null
            )
            ->andReturn($fixture[\TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH]);

        $this->scopeConfig->shouldReceive('getValue')
            ->with(
                \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                null
            )
            ->andReturn($fixture[\TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED]);

        $result = $this->object->getConfig();

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['allow_push']);
    }

    /**
     * Test the getConfig method.
     */
    public function testGetConfigWithStoreId()
    {
        $fixture = [
            \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH => false,
            \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED => false,
        ];

        $this->scopeConfig->shouldReceive('getValue')
            ->with(
                \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                1
            )
            ->andReturn($fixture[\TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ALLOW_PUSH]);

        $this->scopeConfig->shouldReceive('getValue')
            ->with(
                \TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                1
            )
            ->andReturn($fixture[\TIG\Buckaroo\Model\ConfigProvider\Refund::XPATH_REFUND_ENABLED]);

        $result = $this->object->getConfig(1);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['allow_push']);
    }
}
