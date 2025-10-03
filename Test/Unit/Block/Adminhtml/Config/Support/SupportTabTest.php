<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\Config\Support;

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
        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $result = $instance->getVersionNumber();

        // Version should be a string (either empty or semantic version format)
        $this->assertIsString($result);
    }

    public function testPhpVersionCheckIfNothingIsWorking()
    {
        /** @var SupportTab $instance */
        $instance = $this->getInstance();
        $result = $instance->phpVersionCheck();
        $this->assertEquals(-1, $result);
    }

    /**
     * @dataProvider getVersionsDataProvider
     * @return void
     */
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
            ['2.4.5', '8.1, 8.2, 8.3, 8.4', 1],
            ['6.6.6', 'Cannot determine compatible PHP versions', 0]
        ];
    }
}
