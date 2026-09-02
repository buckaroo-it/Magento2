<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\Config\Support;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Block\Adminhtml\Config\Support\SupportTab;
use Buckaroo\Magento2\Service\Software\Data;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\ProductMetadataInterface;

class SupportTabTest extends BaseTest
{
    protected $instanceClass = SupportTab::class;

    public function testGetVersionNumber()
    {
        $softwareDataMock = $this->getFakeMock(Data::class)->getMock();
        $softwareDataMock->method('getModuleVersion')->willReturn(Data::BUCKAROO_VERSION);
        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $result = $instance->getVersionNumber();

        $this->assertEquals(Data::BUCKAROO_VERSION, $result);
    }

    public function testPhpVersionCheckIfNothingIsWorking()
    {
        /** @var SupportTab $instance */
        $instance = $this->getInstance();
        $result = $instance->phpVersionCheck();
        $this->assertEquals(-1, $result);
    }

    /**
     *
     * @param string $version
     * @param string $phpVersions
     * @param int    $returnValue
     */
    #[DataProvider('getVersionsDataProvider')]
    public function testWithDifferentMagentoVersionsAndPhpVersions(string $version, string $phpVersions, int $returnValue)
    {
        $productMetaDataMock = $this->getFakeMock(ProductMetadataInterface::class)->getMock();
        $productMetaDataMock->method('getVersion')->willReturn($version);

        $softwareDataMock = $this->getFakeMock(Data::class)->getMock();
        $softwareDataMock->method('getProductMetaData')->willReturn($productMetaDataMock);

        /** @var SupportTab $instance */
        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $this->assertEquals($phpVersions, $instance->getPhpVersions());
        $this->assertEquals($returnValue, $instance->phpVersionCheck());
    }

    public static function getVersionsDataProvider(): array
    {
        return [
            ['2.4.5', '8.1, 8.2, 8.3, 8.4, 8.5', 1],
            ['6.6.6', 'Cannot determine compatible PHP versions', 0]
        ];
    }
}
