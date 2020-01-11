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
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Gateway\Http\Client;

use Magento\Store\Model\Store;
use TIG\Buckaroo\Gateway\Http\Client\Soap;
use TIG\Buckaroo\Soap\ClientFactory;
use TIG\Buckaroo\Test\BaseTest;

class SoapTest extends BaseTest
{
    protected $instanceClass = Soap::class;

    public function testSetStore()
    {
        $clientFactoryMock = $this->getFakeMock(ClientFactory::class)->setMethods(null)->getMock();
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $instance = $this->getInstance(['clientFactory' => $clientFactoryMock]);
        $result = $instance->setStore($storeMock);

        $this->assertInstanceOf(Soap::class, $result);
        $this->assertEquals($storeMock, $clientFactoryMock->getStore());
    }
}
