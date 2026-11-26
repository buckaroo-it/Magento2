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
                ]
            ]
        ];
    }

    /**
     * @param string $name
     * @param string $edition
     * @param string $version
     * @param array  $module
     * @param array  $expected
     */
    #[DataProvider('getProvider')]
    public function testGet($name, $edition, $version, $module, $expected)
    {
        $productMetadataMock = $this->getFakeMock(ProductMetadataInterface::class)->getMock();
        $productMetadataMock->method('getName')->willReturn($name);
        $productMetadataMock->method('getEdition')->willReturn($edition);
        $productMetadataMock->method('getVersion')->willReturn($version);

        $moduleListMock = $this->getFakeMock(ModuleListInterface::class)->getMock();
        $moduleListMock->expects($this->once())->method('getOne')->with('Buckaroo_Magento2')->willReturn($module);

        $instance = $this->getInstance(['productMetadata' => $productMetadataMock, 'moduleList' => $moduleListMock]);

        $expected['ModuleVersion'] = $this->getReleasedVersion();
        $this->assertSame($expected, $instance->get());
    }

    /**
     * The version reported to Buckaroo is the version the module is released as.
     */
    public function testModuleVersionIsTheReleasedVersion()
    {
        $moduleXml = simplexml_load_file(__DIR__ . '/../../../../etc/module.xml');
        $this->assertNotFalse($moduleXml);

        $version = $this->getInstance()->getModuleVersion();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertSame($this->getReleasedVersion(), $version, 'Version must match composer.json');
        $this->assertSame((string)$moduleXml->module['setup_version'], $version, 'Version must match module.xml');
    }

    /**
     * The version in the module's composer.json, without its "v" prefix.
     *
     * @return string
     */
    private function getReleasedVersion(): string
    {
        $composer = json_decode((string)file_get_contents(__DIR__ . '/../../../../composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertArrayHasKey('version', $composer);

        return ltrim($composer['version'], 'v');
    }
}
