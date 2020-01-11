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
namespace TIG\Buckaroo\Test\Unit\Service\Software;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use TIG\Buckaroo\Service\Software\Data;
use TIG\Buckaroo\Test\BaseTest;

class DataTest extends BaseTest
{
    protected $instanceClass = Data::class;

    /**
     * @return array
     */
    public function getProvider()
    {
        return [
            [
                'Magento',
                'Community',
                '2.0',
                ['name' => 'Buckaroo'],
                [
                    'PlatformName' => 'Magento - Community',
                    'PlatformVersion' => '2.0',
                    'ModuleSupplier' => 'TIG',
                    'ModuleName' => 'Buckaroo',
                    'ModuleVersion' => Data::BUCKAROO_VERSION
                ]
            ],
            [
                'Magento',
                'Enterprise',
                '2.1',
                ['name' => 'Buckaroo'],
                [
                    'PlatformName' => 'Magento - Enterprise',
                    'PlatformVersion' => '2.1',
                    'ModuleSupplier' => 'TIG',
                    'ModuleName' => 'Buckaroo',
                    'ModuleVersion' => Data::BUCKAROO_VERSION
                ]
            ]
        ];
    }

    /**
     * @param $name
     * @param $edition
     * @param $version
     * @param $module
     * @param $expected
     *
     * @dataProvider getProvider
     */
    public function testGet($name, $edition, $version, $module, $expected)
    {
        $productMetadataMock = $this->getFakeMock(ProductMetadataInterface::class)->getMock();
        $productMetadataMock->expects($this->once())->method('getName')->willReturn($name);
        $productMetadataMock->expects($this->once())->method('getEdition')->willReturn($edition);
        $productMetadataMock->expects($this->once())->method('getVersion')->willReturn($version);

        $moduleListMock = $this->getFakeMock(ModuleListInterface::class)->getMock();
        $moduleListMock->expects($this->once())->method('getOne')->with(Data::MODULE_CODE)->willReturn($module);

        $instance = $this->getInstance(['productMetadata' => $productMetadataMock, 'moduleList' => $moduleListMock]);
        $result = $instance->get();

        $this->assertEquals($expected, $result);
    }

    public function testGetProductMetaData()
    {
        $instance = $this->getInstance();
        $result = $instance->getProductMetaData();

        $this->assertInstanceOf(ProductMetadataInterface::class, $result);
    }
}
