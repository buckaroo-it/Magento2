<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider;

use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies;

class AllowedCurrenciesTest extends BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    public function testSetAllowedCurrencies()
    {
        $randomArray = [rand(1, 1000)];

        $instance = $this->getInstance();
        $result = $instance->setAllowedCurrencies($randomArray);

        $this->assertInstanceOf(AllowedCurrencies::class, $result);
        $this->assertEquals($randomArray, $instance->getAllowedCurrencies());
    }

    public function testGetConfig()
    {
        $instance = $this->getInstance();
        $result = $instance->getConfig();

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('allowedCurrencies', $result);
        $this->assertCount(22, $result['allowedCurrencies']);
    }
}
