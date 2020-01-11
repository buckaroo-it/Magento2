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
namespace TIG\Buckaroo\Service\Software;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;

class Data
{
    /** Module supplier */
    const MODULE_SUPPLIER = 'TIG';

    /** Module code */
    const MODULE_CODE = 'TIG_Buckaroo';

    /** Version of Module */
    const BUCKAROO_VERSION = '1.9.0';

    /** @var ProductMetadataInterface */
    private $productMetadata;

    /** @var ModuleListInterface */
    private $moduleList;

    public function __construct(
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList
    ) {
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    /**
     * @return array
     */
    public function get()
    {
        $platformData = $this->getPlatformData();
        $moduleData = $this->getModuleData();

        $softwareData = array_merge($platformData, $moduleData);

        return $softwareData;
    }

    /**
     * @return ProductMetadataInterface
     */
    public function getProductMetaData()
    {
        return $this->productMetadata;
    }

    /**
     * @return array
     */
    private function getPlatformData()
    {
        $platformName = $this->getProductMetaData()->getName() . ' - ' . $this->getProductMetaData()->getEdition();

        $platformData = [
            'PlatformName' => $platformName,
            'PlatformVersion' => $this->productMetadata->getVersion()
        ];

        return $platformData;
    }

    /**
     * @return array
     */
    private function getModuleData()
    {
        $module = $this->moduleList->getOne(self::MODULE_CODE);

        $moduleData = [
            'ModuleSupplier'    => self::MODULE_SUPPLIER,
            'ModuleName'        => $module['name'],
            'ModuleVersion'     => self::BUCKAROO_VERSION
        ];

        return $moduleData;
    }
}
