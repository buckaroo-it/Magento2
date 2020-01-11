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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source;

class AllowedCurrenciesTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var \TIG\Buckaroo\Model\Config\Source\AllowedCurrencies
     */
    protected $object;

    /**
     * @var \Mockery\MockInterface
     */
    protected $resource;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies|\Mockery\MockInterface
     */
    protected $allowedCurrenciesConfig;

    /**
     * @var \Magento\Framework\Locale\Bundle\CurrencyBundle|\Mockery\MockInterface
     */
    protected $currencyBundle;

    /**
     * @var array
     */
    protected $fixture = [
        'ABC' => [1 => 'Alphabet'],
        'DEF' => [1 => 'DOOF'],
        'GHI' => [1 => 'GHIGHI'],
    ];

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->resource = \Mockery::mock(\Magento\Framework\Model\ResourceModel\AbstractResource::class);
        $this->resource->shouldReceive('save');

        $this->allowedCurrenciesConfig = \Mockery::mock(\TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies::class)
            ->makePartial();
        $this->allowedCurrenciesConfig->shouldReceive('getAllowedCurrencies')->andReturn(['ABC', 'DEF', 'GHI']);

        $this->currencyBundle = \Mockery::mock(\Magento\Framework\Locale\Bundle\CurrencyBundle::class)->makePartial();
        $this->currencyBundle->shouldReceive('get')->andReturn(
            [
            'Currencies' => $this->fixture,
            ]
        );

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Config\Source\AllowedCurrencies::class,
            [
                'resource' => $this->resource,
                'currencyBundle' => $this->currencyBundle,
                'allowedCurrenciesConfig' => $this->allowedCurrenciesConfig,
            ]
        );
    }

    /**
     * Test what happens when there is no value provided.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testSaveNoValue()
    {
        $result = $this->object->toOptionArray();

        $this->assertTrue(is_array($result));
        $this->assertEquals(3, count($result));

        $count = 0;
        foreach ($this->fixture as $key => $data) {
            $this->assertEquals($key, $result[$count]['value']);
            $this->assertEquals($data[1], $result[$count]['label']);
            $count++;
        }
    }
}
