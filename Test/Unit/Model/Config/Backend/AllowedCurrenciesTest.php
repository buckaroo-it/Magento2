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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use TIG\Buckaroo\Model\Config\Backend\AllowedCurrencies;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies as AllowedCurrenciesProvider;

class AllowedCurrenciesTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    /**
     * Test what happens when there is no value provided.
     */
    public function testSaveNoValue()
    {
        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

        $instance = $this->getInstance(['resource' => $resourceMock]);

        $result = $instance->save();
        $this->assertInstanceOf(AllowedCurrencies::class, $result);
    }

    /**
     * Test what happens when there is a valid value provided.
     */
    public function testSaveWithValidValue()
    {
        $configProviderMock = $this->getFakeMock(AllowedCurrenciesProvider::class)
            ->setMethods(['getAllowedCurrencies'])
            ->getMock();
        $configProviderMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['EUR', 'USD']);

        $resourceMock = $this->getFakeMock(AbstractResource::class)->setMethods(['save'])->getMockForAbstractClass();
        $resourceMock->expects($this->once())->method('save');

        $instance = $this->getInstance(['configProvider' => $configProviderMock, 'resource' => $resourceMock]);
        $instance->setValue(['EUR']);

        $result = $instance->save();
        $this->assertInstanceOf(AllowedCurrencies::class, $result);
    }

    /**
     * Test what happens when there is a invalid value provided.
     */
    public function testSaveWithInvalidValue()
    {
        $configProviderMock = $this->getFakeMock(AllowedCurrenciesProvider::class)
            ->setMethods(['getAllowedCurrencies'])
            ->getMock();
        $configProviderMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['EUR', 'USD']);

        $instance = $this->getInstance(['configProvider' => $configProviderMock]);
        $instance->setValue(['GBP']);

        try {
            $instance->save();
        } catch (LocalizedException $e) {
            $this->assertEquals("Please enter a valid currency: 'GBP'.", $e->getMessage());
        }
    }

    /**
     * Test what happens when there is a invalid value provided.
     *
     * @throws LocalizedException
     */
    public function testSaveWithInvalidValueThatHasCurrency()
    {
        $currencyBundleData = [
            'Currencies' => [
                'GBP' => [
                    1 => 'British Pound',
                ]
            ]
        ];

        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->setMethods(['get'])->getMock();
        $currencyBundleMock->expects($this->once())->method('get')->willReturn($currencyBundleData);

        $configProviderMock = $this->getFakeMock(AllowedCurrenciesProvider::class)
            ->setMethods(['getAllowedCurrencies'])
            ->getMock();
        $configProviderMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['EUR', 'USD']);

        $instance = $this->getInstance([
            'currencyBundle' => $currencyBundleMock,
            'configProvider' => $configProviderMock
        ]);
        $instance->setValue(['GBP']);

        try {
            $instance->save();
        } catch (LocalizedException $e) {
            $this->assertEquals("Please enter a valid currency: 'British Pound'.", $e->getMessage());
        }
    }
}
