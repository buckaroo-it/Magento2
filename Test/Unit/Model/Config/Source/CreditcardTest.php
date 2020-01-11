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

use TIG\Buckaroo\Model\Config\Source\Creditcard;
use TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard as CreditcardProvider;

class CreditcardTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Creditcard::class;

    public function testToOptionArray()
    {
        $issuers = [
            [
                'name' => 'Test 1',
                'code' => 'code1',
            ],
            [
                'name' => 'Test 2',
                'code' => 'code2',
            ],
            [
                'name' => 'Test 3',
                'code' => 'code3',
            ],
        ];

        $configProviderMock = $this->getFakeMock(CreditcardProvider::class)->setMethods(['getIssuers'])->getMock();
        $configProviderMock->expects($this->once())->method('getIssuers')->willReturn($issuers);

        $instance = $this->getInstance(['configProvider' => $configProviderMock]);
        $result = $instance->toOptionArray();

        $expected = [
            [
                'value' => 'code1',
                'label' => 'Test 1',
            ],
            [
                'value' => 'code2',
                'label' => 'Test 2',
            ],
            [
                'value' => 'code3',
                'label' => 'Test 3',
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
