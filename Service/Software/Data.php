<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Software;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;

class Data
{
    /**
     * Module supplier name
     */
    public const MODULE_SUPPLIER = 'Buckaroo';

    /**
     * Module supplier code
     */
    public const MODULE_CODE = 'Buckaroo_Magento2';

    /** Version of Module */
    const BUCKAROO_VERSION = '1.45.0';

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;

    /**
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList
    ) {
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    /**
     * Returns an array containing the software data for both the platform and the module.
     *
     * @return array
     */
    public function get(): array
    {
        $platformData = $this->getPlatformData();
        $moduleData = $this->getModuleData();

        return array_merge($platformData, $moduleData);
    }

    /**
     * Retrieves and returns an array containing the platform data.
     *
     * @return array
     */
    private function getPlatformData(): array
    {
        $platformName = $this->getProductMetaData()->getName() . ' - ' . $this->getProductMetaData()->getEdition();

        return [
            'PlatformName'    => $platformName,
            'PlatformVersion' => $this->productMetadata->getVersion()
        ];
    }

    /**
     * Returns the product metadata object for the platform.
     *
     * @return ProductMetadataInterface
     */
    public function getProductMetaData(): ProductMetadataInterface
    {
        return $this->productMetadata;
    }

    /**
     * Retrieves and returns an array containing the module data.
     *
     * @return array
     */
    private function getModuleData(): array
    {
        $module = $this->moduleList->getOne(self::MODULE_CODE);

        return [
            'ModuleSupplier' => self::MODULE_SUPPLIER,
            'ModuleName'     => $module['name'],
            'ModuleVersion'  => $this->getModuleVersion()
        ];
    }

    /**
     * Returns the module version as a string.
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return self::BUCKAROO_VERSION;
    }
}
