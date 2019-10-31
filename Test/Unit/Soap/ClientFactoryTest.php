<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
namespace TIG\Buckaroo\Test\Unit\Soap;

use Magento\Store\Model\Store;
use TIG\Buckaroo\Soap\ClientFactory;
use TIG\Buckaroo\Test\BaseTest;

class ClientFactoryTest extends BaseTest
{
    protected $instanceClass = ClientFactory::class;

    public function testGetStore()
    {
        $storeMock = $this->getFakeMock(Store::class)->getMock();

        $instance = $this->getInstance();
        $instance->setStore($storeMock);
        $result = $instance->getStore();

        $this->assertEquals($storeMock, $result);
    }
}
