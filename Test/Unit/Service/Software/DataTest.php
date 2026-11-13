<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Test\Unit\Service\Software;


use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Buckaroo\Magento2\Service\Software\Data;
use Buckaroo\Magento2\Test\BaseTest;

class DataTest extends BaseTest
{
    protected $instanceClass = Data::class;

    /**
     * @return array
     */
    public static function getProvider()
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
                    'ModuleSupplier' => 'Buckaroo',
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
                    'ModuleSupplier' => 'Buckaroo',
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
     */
    #[DataProvider('getProvider')]
    public function testGet($name, $edition, $version, $module, $expected)
    {
        $productMetadataMock = $this->getFakeMock(ProductMetadataInterface::class)->getMock();
        $productMetadataMock->method('getName')->willReturn($name);
        $productMetadataMock->method('getEdition')->willReturn($edition);
        $productMetadataMock->method('getVersion')->willReturn($version);

        $moduleListMock = $this->getFakeMock(ModuleListInterface::class)->getMock();
        $moduleListMock->method('getOne')->with(Data::MODULE_CODE)->willReturn($module);

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

    public function testGetModuleVersion()
    {
        $instance = $this->getInstance();
        $result = $instance->getModuleVersion();
        $this->assertEquals(Data::BUCKAROO_VERSION, $result);
    }
}
